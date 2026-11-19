<?php

use App\Enums\DigestFrequency;
use App\Enums\Language;
use App\Filament\Pages\Calendar;
use App\Filament\Resources\Assets\RelationManagers\RemindersRelationManager;
use App\Filament\Widgets\RemindersDue;
use App\Models\AssetReminder;
use App\Models\Category;
use App\Models\Company;
use App\Models\Role;
use App\Notifications\Notice;
use Database\Seeders\RoleSeeder;
use Database\Seeders\StatusSeeder;
use Filament\Actions\CreateAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Notification;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed([RoleSeeder::class, StatusSeeder::class]);
    Filament::setCurrentPanel('admin');
    $this->travelTo('2027-01-04 07:00');

    $this->tcm = Company::factory()->create();
    $this->cars = Category::factory()->create(['nama' => 'Kendaraan', 'role_id' => Role::findByName('admin_ga')->id]);
    $this->laptops = Category::factory()->create(['role_id' => Role::findByName('admin_it')->id]);
    $this->car = availableAsset($this->tcm, $this->cars, ['nama' => 'Avanza B 1234 XYZ']);
    $this->adminGa = userWithRole('admin_ga', attributes: ['company_id' => $this->tcm->id]);
    $this->adminIt = userWithRole('admin_it', attributes: ['company_id' => $this->tcm->id]);
    $this->actingAs($this->adminGa);
});

function reminderManager(): mixed
{
    return livewire(RemindersRelationManager::class, ['ownerRecord' => test()->car, 'pageClass' => EditAsset::class]);
}

test('an admin adds a reminder on the asset page, and marking a yearly one done moves it a year ahead', function () {
    reminderManager()
        ->callAction(TestAction::make(CreateAction::class)->table(), data: ['jenis' => 'Pajak STNK', 'tanggal' => '2027-01-31', 'ulangi_bulan' => 12])
        ->assertHasNoActionErrors();

    $reminder = AssetReminder::query()->sole();

    reminderManager()->callAction(TestAction::make('complete')->table($reminder))->assertNotified('Pengingat diperbarui');

    expect($reminder->refresh()->tanggal->toDateString())->toBe('2028-01-31')
        ->and($reminder->selesai_pada)->toBeNull();
});

test('a monthly reminder on the 31st does not spill into the next month, and a one-off reminder closes', function () {
    $monthly = AssetReminder::factory()->create(['asset_id' => $this->car->id, 'tanggal' => '2027-01-31', 'ulangi_bulan' => 1]);
    $oneOff = AssetReminder::factory()->create(['asset_id' => $this->car->id, 'jenis' => 'Akhir kontrak sewa']);

    $monthly->complete();
    $oneOff->complete();

    expect($monthly->tanggal->toDateString())->toBe('2027-02-28')
        ->and($oneOff->selesai_pada)->not->toBeNull()
        ->and(fn () => $oneOff->complete())->toThrow(LogicException::class, 'Pengingat ini sudah selesai.');
});

test('the managing admins get an email 30 and 7 days before, in their own language, and nobody else', function () {
    Notification::fake();
    $this->adminGa->update(['locale' => Language::English]);
    AssetReminder::factory()->create(['asset_id' => $this->car->id, 'jenis' => 'Pajak STNK', 'tanggal' => today()->addDays(30)]);
    AssetReminder::factory()->create(['asset_id' => $this->car->id, 'jenis' => 'Servis berkala', 'tanggal' => today()->addDays(7)]);
    AssetReminder::factory()->create(['asset_id' => $this->car->id, 'tanggal' => today()->addDays(10)]);
    AssetReminder::factory()->create(['asset_id' => $this->car->id, 'tanggal' => today()->addDays(7), 'selesai_pada' => now()]);

    $this->artisan('pengingat:kirim')->assertSuccessful();

    Notification::assertSentToTimes($this->adminGa, Notice::class, 2);
    Notification::assertSentTo($this->adminGa, Notice::class, fn (Notice $notice): bool => $notice->subject === "Servis berkala: {$this->car->kode}, 11 January 2027"
        && str_ends_with($notice->intro, 'due on 11 January 2027, 7 days from now.'));
    Notification::assertNotSentTo($this->adminIt, Notice::class);
});

test('upcoming reminders show on the calendar, the dashboard, and the email digest, only for the admins who manage the asset', function () {
    Notification::fake();
    $reminder = AssetReminder::factory()->create(['asset_id' => $this->car->id, 'jenis' => 'Pajak STNK', 'tanggal' => today()->addDays(10)]);
    $done = AssetReminder::factory()->create(['asset_id' => $this->car->id, 'jenis' => 'Perpanjangan asuransi', 'tanggal' => today()->addDays(5), 'selesai_pada' => now()]);
    $this->adminGa->update(['digest_frequency' => DigestFrequency::Mingguan]);

    livewire(Calendar::class)->assertSee(['Pengingat', 'Pajak STNK', $this->car->kode])->assertDontSee('Perpanjangan asuransi');
    livewire(RemindersDue::class)->assertCanSeeTableRecords([$reminder])->assertCanNotSeeTableRecords([$done]);

    $this->artisan('notifikasi:ringkasan')->assertSuccessful();
    Notification::assertSentTo($this->adminGa, Notice::class, function (Notice $notice): bool {
        $lines = collect($notice->sections)->flatten();

        return $lines->contains(fn (string $line): bool => str_starts_with($line, 'Pajak STNK: '))
            && ! $lines->contains(fn (string $line): bool => str_starts_with($line, 'Perpanjangan asuransi: '));
    });

    $this->actingAs($this->adminIt);

    livewire(RemindersDue::class)->assertCanNotSeeTableRecords([$reminder]);
    livewire(Calendar::class)->assertDontSee('Pajak STNK');
});

test('only users who may change the asset add or complete its reminders', function () {
    $reminder = AssetReminder::factory()->create(['asset_id' => $this->car->id]);
    Role::findByName('admin_ga')->revokePermissionTo('Update:Asset');
    $this->actingAs($this->adminGa->refresh());

    reminderManager()
        ->assertActionHidden(TestAction::make(CreateAction::class)->table())
        ->assertActionHidden(TestAction::make('complete')->table($reminder));
});
