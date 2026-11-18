<?php

use App\Filament\Pages\Settings;
use App\Models\AssetRequest;
use App\Models\Category;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Role;
use App\Models\Setting;
use App\Notifications\Channels\TelegramChannel;
use App\Notifications\Notice;
use Database\Seeders\RoleSeeder;
use Database\Seeders\StatusSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Http\Client\Request;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed([RoleSeeder::class, StatusSeeder::class]);
    Filament::setCurrentPanel('admin');
    config(['services.telegram.bot_token' => '123456:RAHASIA-bot', 'services.telegram.chat_id' => '-1001234567890']);
});

function sendToGroup(Notice $notice): void
{
    TelegramChannel::adminGroup()->notifyNow($notice);
}

function requestLaptop(): void
{
    $tcm = Company::factory()->create();
    $laptops = Category::factory()->create(['role_id' => Role::findByName('admin_it')->id]);
    test()->adminIt = userWithRole('admin_it', attributes: ['company_id' => $tcm->id]);
    availableAsset($tcm, $laptops, ['requestable' => true]);

    AssetRequest::submit(Employee::factory()->create(['company_id' => $tcm->id]), $laptops, 'Laptop lama sering mati');
}

test('with Telegram switched on, a new asset request also goes to the admin group', function () {
    Notification::fake();
    Setting::put('telegram_enabled', true);

    requestLaptop();

    Notification::assertSentTo($this->adminIt, Notice::class);
    Notification::assertSentOnDemand(Notice::class, fn (Notice $notice, array $channels, AnonymousNotifiable $notifiable): bool => $channels === [TelegramChannel::class]
        && $notifiable->routes['telegram'] === '-1001234567890'
        && str_starts_with($notice->subject, 'Permintaan aset baru'));
});

test('Telegram stays quiet while switched off or not set up on the server', function (bool $enabled, ?string $token) {
    Notification::fake();
    Setting::put('telegram_enabled', $enabled);
    config(['services.telegram.bot_token' => $token]);

    requestLaptop();

    Notification::assertSentTo($this->adminIt, Notice::class);
    Notification::assertSentOnDemandTimes(Notice::class, 0);
})->with([
    'switched off' => [false, '123456:RAHASIA-bot'],
    'no bot token' => [true, null],
]);

test('the message is posted to the group as escaped HTML', function () {
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

    sendToGroup(new Notice('Laporan <b>baru</b>', 'Rina & Budi', ['Keluhan' => ['<script>alert(1)</script>']], 'Buka', 'https://aset.test/a?b=1&c=2'));

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.telegram.org/bot123456:RAHASIA-bot/sendMessage'
        && $request['chat_id'] === '-1001234567890'
        && $request['parse_mode'] === 'HTML'
        && $request['text'] === "<b>Laporan &lt;b&gt;baru&lt;/b&gt;</b>\n\nRina &amp; Budi\n\n<b>Keluhan</b>\n• &lt;script&gt;alert(1)&lt;/script&gt;\n\n<a href=\"https://aset.test/a?b=1&amp;c=2\">Buka</a>");
});

test('a failed send gives the reason without leaking the bot token', function (Closure $response, string $reason) {
    Http::fake(['api.telegram.org/*' => $response()]);

    expect(fn () => sendToGroup(Notice::telegramTest()))
        ->toThrow(fn (RuntimeException $exception) => expect($exception->getMessage())
            ->toBe("Telegram gagal: {$reason}")
            ->not->toContain('RAHASIA'));
})->with([
    'rejected' => [fn () => Http::response(['ok' => false, 'description' => 'Bad Request: chat not found'], 400), 'Bad Request: chat not found'],
    'unreachable' => [fn () => Http::failedConnection('cURL error 28 for https://api.telegram.org/bot123456:RAHASIA-bot/sendMessage'), 'server Telegram tidak bisa dihubungi'],
]);

test('a super admin sends a test message from Settings, which needs the bot set up on the server', function () {
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
    $this->actingAs(userWithRole('super_admin'));
    $testAction = TestAction::make('telegramTest')->schemaComponent('telegram_enabled', 'form');

    livewire(Settings::class)
        ->callAction($testAction)
        ->assertNotified('Pesan uji terkirim ke grup Telegram');

    Http::assertSentCount(1);

    config(['services.telegram.chat_id' => null]);

    livewire(Settings::class)
        ->assertDontSee('Kirim pesan uji')
        ->assertSee('Belum bisa dipakai: TELEGRAM_BOT_TOKEN dan TELEGRAM_CHAT_ID belum diisi di server.');
});
