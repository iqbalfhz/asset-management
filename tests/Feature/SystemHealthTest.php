<?php

use App\Enums\DisposalMethod;
use App\Filament\Widgets\SystemHealth;
use App\Models\Category;
use App\Models\Company;
use App\Models\Disposal;
use App\Models\ErrorLog;
use App\Models\Role;
use App\Models\Status;
use Database\Seeders\RoleSeeder;
use Database\Seeders\StatusSeeder;
use Filament\Facades\Filament;
use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed([RoleSeeder::class, StatusSeeder::class]);
    Filament::setCurrentPanel('admin');
    $this->travelTo('2026-11-20 10:00');
    Storage::fake('local');

    $this->actingAs($this->superAdmin = userWithRole('super_admin'));
});

function backupMadeAt(string $time): void
{
    Storage::disk('local')->put(config('backup.backup.name').'/'.now()->parse($time)->format('Y-m-d-H-i-s').'.zip', 'zip');
}

function failedJob(string $exception = "Symfony\\Component\\Mailer\\Exception\\TransportException: API key is invalid\n#0 trace"): void
{
    DB::table('failed_jobs')->insert([
        'uuid' => (string) Str::uuid(),
        'connection' => 'database',
        'queue' => 'default',
        'payload' => json_encode(['uuid' => (string) Str::uuid(), 'displayName' => 'App\\Notifications\\Notice', 'attempts' => 3, 'data' => []]),
        'exception' => $exception,
        'failed_at' => now(),
    ]);
}

function failure(string $message): RuntimeException
{
    return new RuntimeException($message);
}

/**
 * @return array<string, bool>
 */
function healthStates(): array
{
    return collect((new SystemHealth)->checks())->map(fn (array $check): bool => $check['ok'])->all();
}

test('only a super admin sees system health', function () {
    expect(SystemHealth::canView())->toBeTrue();

    $this->actingAs(userWithRole('admin_it'));

    expect(SystemHealth::canView())->toBeFalse();
});

test('everything is normal within the limits', function () {
    backupMadeAt('2026-11-19 09:00');
    Cache::forever(SystemHealth::HEARTBEAT, now()->subMinutes(4)->getTimestamp());

    expect(healthStates())->toBe(['backup' => true, 'scheduler' => true, 'jobs' => true, 'errors' => true, 'disposals' => true]);

    livewire(SystemHealth::class)
        ->assertSee('Kesehatan sistem')
        ->assertDontSee('Perlu dicek')
        ->assertActionHidden('retryFailedJobs');
});

test('each check turns red past its limit and says why', function () {
    backupMadeAt('2026-11-19 07:59');
    Cache::forever(SystemHealth::HEARTBEAT, now()->subMinutes(6)->getTimestamp());
    failedJob();
    ErrorLog::record(failure('Gagal membuat PDF'));
    $broken = availableAsset(Company::factory()->create(), Category::factory()->create(['role_id' => Role::findByName('admin_it')->id]));
    $broken->transitionTo(Status::named(Status::RUSAK));
    Disposal::submit($broken, ['alasan' => 'Rusak', 'metode' => DisposalMethod::Dibuang]);

    expect(healthStates())->toBe(['backup' => false, 'scheduler' => false, 'jobs' => false, 'errors' => false, 'disposals' => false]);

    livewire(SystemHealth::class)
        ->assertSee('API key is invalid')
        ->assertDontSee('#0 trace')
        ->assertSee('Gagal membuat PDF')
        ->assertSee('Cek Scheduled Task di Coolify')
        ->assertActionVisible('retryFailedJobs');
});

test('a missing backup or heartbeat is red, not normal', function () {
    $checks = (new SystemHealth)->checks();

    expect($checks['backup'])->toMatchArray(['ok' => false, 'value' => 'Belum ada'])
        ->and($checks['scheduler'])->toMatchArray(['ok' => false, 'value' => 'Belum pernah jalan']);
});

test('retrying puts the failed jobs back on the queue', function () {
    failedJob();
    failedJob();

    livewire(SystemHealth::class)
        ->callAction('retryFailedJobs')
        ->assertNotified('Job dimasukkan lagi ke antrean');

    expect(DB::table('failed_jobs')->count())->toBe(0)
        ->and(DB::table('jobs')->count())->toBe(2);
});

test('an admin who is not a super admin cannot load the widget to retry failed jobs', function () {
    failedJob();
    $this->actingAs(userWithRole('admin_it'));

    livewire(SystemHealth::class)->assertForbidden();
});

test('the same error is grouped and counted, without the SQL and its values', function () {
    ErrorLog::record(failure('Pertama'));
    ErrorLog::record(failure('Kedua'));
    ErrorLog::record(new QueryException('mysql', 'insert into users (email) values (?)', ['rahasia@contoh.test'], new Exception('SQLSTATE[23000]: Duplicate entry')));

    $grouped = ErrorLog::query()->where('jenis', RuntimeException::class)->sole();

    expect($grouped->jumlah)->toBe(2)
        ->and($grouped->pesan)->toBe('Kedua')
        ->and($grouped->lokasi)->toStartWith('tests'.DIRECTORY_SEPARATOR.'Feature'.DIRECTORY_SEPARATOR.'SystemHealthTest.php:')
        ->and(ErrorLog::query()->where('jenis', QueryException::class)->value('pesan'))->not->toContain('rahasia');
});

test('an error during a request is recorded with its page and user, but a 404 is not', function () {
    Route::middleware('web')->get('_test/rusak', fn () => throw failure('Halaman rusak'));

    $this->get('_test/rusak')->assertServerError();
    $this->get('_test/tidak-ada')->assertNotFound();

    expect(ErrorLog::query()->sole())
        ->url->toBe('_test/rusak')
        ->user_id->toBe($this->superAdmin->id);
});

test('errors are kept 90 days after they last happened', function () {
    ErrorLog::record(failure('Lama'));
    $this->travel(91)->days();
    ErrorLog::record(new LogicException('Baru'));

    $this->artisan('model:prune', ['--model' => [ErrorLog::class]])->assertSuccessful();

    expect(ErrorLog::query()->pluck('pesan')->all())->toBe(['Baru']);
});

test('the scheduler leaves a heartbeat every minute', function () {
    $heartbeat = collect(app(Schedule::class)->events())->first(fn ($event): bool => $event->description === 'detak-scheduler');

    expect($heartbeat)->toBeInstanceOf(CallbackEvent::class)
        ->and($heartbeat->expression)->toBe('* * * * *');

    $heartbeat->run(app());

    expect(Cache::get(SystemHealth::HEARTBEAT))->toBe(now()->getTimestamp());
});
