<?php

use App\Filament\Pages\ActivityLog;
use App\Filament\Resources\Assets\Pages\ListAssets;
use App\Http\Middleware\LogDownload;
use App\Models\Assignment;
use App\Models\Category;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Role;
use Database\Seeders\RoleSeeder;
use Database\Seeders\StatusSeeder;
use Filament\Actions\ExportAction;
use Filament\Facades\Filament;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed([RoleSeeder::class, StatusSeeder::class]);
    Filament::setCurrentPanel('admin');

    $this->tcm = Company::factory()->create(['kode' => 'TCM']);
    $this->adminIt = userWithRole('admin_it', attributes: ['company_id' => $this->tcm->id]);
    $this->actingAs($this->adminIt);

    $asset = availableAsset($this->tcm, Category::factory()->create(['role_id' => Role::findByName('admin_it')->id]));
    Assignment::handOver(new Collection([$asset]), Employee::factory()->create(['company_id' => $this->tcm->id]), ['tanggal' => '2026-11-20', 'kondisi' => 'Baik']);
    $this->assignment = $asset->assignments()->sole();
});

function downloadLogs(): Collection
{
    return Activity::query()->where('log_name', LogDownload::LOG)->get();
}

test('downloading a BAST is logged with who took it, the document, and the hand-over', function () {
    $this->get(route('filament.admin.bast.download', $this->assignment))->assertOk();

    $log = downloadLogs()->sole();

    expect($log->event)->toBe('diunduh')
        ->and($log->causer->is($this->adminIt))->toBeTrue()
        ->and($log->subject->is($this->assignment))->toBeTrue()
        ->and($log->properties->all())->toBe(['dokumen' => 'BAST']);
});

test('the activity log can be narrowed to downloads', function () {
    $this->get(route('filament.admin.bast.download', $this->assignment));
    $this->actingAs(userWithRole('super_admin'));

    livewire(ActivityLog::class)
        ->filterTable('event', 'diunduh')
        ->assertCanSeeTableRecords(downloadLogs())
        ->assertCanNotSeeTableRecords(Activity::query()->where('log_name', '!=', LogDownload::LOG)->get())
        ->assertSee('dokumen: BAST');
});

test('a refused download is not logged', function () {
    $this->actingAs(userWithRole('admin_ga', attributes: ['company_id' => $this->tcm->id]));

    $this->get(route('filament.admin.bast.download', $this->assignment))->assertNotFound();

    expect(downloadLogs())->toBeEmpty();
});

test('the asset report download keeps the chosen filters', function () {
    $this->actingAs(userWithRole('super_admin'));

    $this->get(route('filament.admin.reports.assets', ['perusahaan' => $this->tcm->id, 'tanggal' => '2026-11-20']))->assertOk();

    expect(downloadLogs()->sole()->properties->all())
        ->toBe(['dokumen' => 'Laporan aset', 'perusahaan' => (string) $this->tcm->id, 'tanggal' => '2026-11-20']);
});

test('every document route records its downloads', function (string $route) {
    $middleware = Route::getRoutes()->getByName($route)->gatherMiddleware();

    expect(collect($middleware)->contains(fn (string $name): bool => str_starts_with($name, LogDownload::class.':')))->toBeTrue();
})->with([
    'filament.admin.attachments.download',
    'filament.admin.bast.download',
    'filament.admin.maintenances.photo',
    'filament.admin.disposals.report',
    'filament.admin.audits.report',
    'filament.admin.reports.assets',
    'filament.karyawan.bast.download',
]);

test('requesting an Excel export is logged with the data and the row count', function () {
    ExportAction::fake();

    livewire(ListAssets::class)->callAction('export')->assertHasNoFormErrors();

    $log = downloadLogs()->sole();

    expect($log->event)->toBe('diekspor')
        ->and($log->causer->is($this->adminIt))->toBeTrue()
        ->and($log->properties->all())->toBe(['dokumen' => 'Export Excel', 'data' => 'Asset', 'baris' => 1]);
});

test('download logs are kept two years while change logs are not touched', function () {
    $event = collect(app(Schedule::class)->events())->first(fn (Event $event): bool => str_contains($event->command, 'activitylog:clean'));

    $this->get(route('filament.admin.bast.download', $this->assignment));
    $this->travel(731)->days();
    $this->get(route('filament.admin.bast.download', $this->assignment));
    $changeLogs = Activity::query()->where('log_name', '!=', LogDownload::LOG)->count();

    // Perintah persis seperti yang dijalankan jadwal, supaya opsi yang salah ketahuan di sini, bukan di server.
    $this->artisan('activitylog:clean'.Str::after($event->command, 'activitylog:clean'))->assertSuccessful();

    expect(downloadLogs())->toHaveCount(1)
        ->and(Activity::query()->where('log_name', '!=', LogDownload::LOG)->count())->toBe($changeLogs);
});
