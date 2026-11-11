<?php

use App\Enums\MaintenanceStatus;
use App\Filament\Karyawan\Pages\MyAssets;
use App\Filament\Resources\Maintenances\Pages\CreateMaintenance;
use App\Filament\Resources\Maintenances\Pages\ListMaintenances;
use App\Models\Asset;
use App\Models\Assignment;
use App\Models\Category;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Maintenance;
use App\Models\Role;
use App\Models\Status;
use Database\Seeders\RoleSeeder;
use Database\Seeders\StatusSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed([RoleSeeder::class, StatusSeeder::class]);
    Filament::setCurrentPanel('admin');
    Storage::fake('local');

    $this->tcm = Company::factory()->create();
    $this->laptopCategory = Category::factory()->create(['role_id' => Role::findByName('admin_it')->id]);
    $this->adminIt = userWithRole('admin_it', attributes: ['company_id' => $this->tcm->id]);
    $this->employeeUser = userWithRole('karyawan', 'karyawan', ['company_id' => $this->tcm->id]);
    $this->employee = Employee::factory()->create(['company_id' => $this->tcm->id, 'user_id' => $this->employeeUser->id]);

    $this->laptop = Asset::factory()->create([
        'company_id' => $this->tcm->id,
        'category_id' => $this->laptopCategory->id,
        'status_id' => Status::named(Status::TERSEDIA)->id,
    ]);

    $this->actingAs($this->adminIt);
    Assignment::handOver(new Collection([$this->laptop]), $this->employee, ['tanggal' => now()->toDateString(), 'kondisi' => 'Baik']);
    $this->report = Maintenance::report($this->employee, $this->laptop->refresh(), 'Layar berkedip', 'maintenances/layar.jpg');
});

test('a report taken into repair puts the asset in repair, and finishing gives it back to the same holder', function () {
    livewire(ListMaintenances::class)
        ->callAction(TestAction::make('process')->table($this->report), data: ['tgl_mulai' => now()->toDateString()])
        ->assertNotified('Perbaikan diproses');

    expect($this->laptop->refresh()->status->nama)->toBe(Status::DALAM_PERBAIKAN)
        ->and($this->report->refresh()->status)->toBe(MaintenanceStatus::Diproses);

    livewire(ListMaintenances::class)
        ->callAction(TestAction::make('finish')->table($this->report), data: ['hasil' => 'Ganti panel layar', 'biaya' => 750000, 'tgl_selesai' => now()->toDateString()])
        ->assertNotified('Perbaikan selesai');

    $this->laptop->refresh();

    expect($this->laptop->status->nama)->toBe(Status::DIPAKAI)
        ->and($this->laptop->assigned->is($this->employee))->toBeTrue()
        ->and($this->report->refresh()->status)->toBe(MaintenanceStatus::Selesai)
        ->and($this->report->biaya)->toBe(750000);
});

test('a repair that fails leaves the asset broken and off the employee\'s list', function () {
    $this->report->process([]);

    livewire(ListMaintenances::class)
        ->callAction(TestAction::make('markBroken')->table($this->report), data: ['hasil' => 'Motherboard mati', 'tgl_selesai' => now()->toDateString()]);

    expect($this->laptop->refresh()->status->nama)->toBe(Status::RUSAK)
        ->and($this->report->refresh()->status)->toBe(MaintenanceStatus::Rusak);

    $this->actingAs($this->employeeUser);
    Filament::setCurrentPanel('karyawan');

    livewire(MyAssets::class)->assertCanNotSeeTableRecords([$this->laptop]);
});

test('a rejected report keeps the asset as it was and carries the admin\'s note', function () {
    livewire(ListMaintenances::class)
        ->callAction(TestAction::make('reject')->table($this->report), data: ['catatan_admin' => 'Layar normal saat dicek'])
        ->assertNotified('Laporan ditolak');

    expect($this->report->refresh()->status)->toBe(MaintenanceStatus::Ditolak)
        ->and($this->report->catatan_admin)->toBe('Layar normal saat dicek')
        ->and($this->laptop->refresh()->status->nama)->toBe(Status::DIPAKAI);
});

test('each step is refused from the wrong status', function () {
    expect(fn () => $this->report->finish(['hasil' => 'Selesai']))->toThrow(LogicException::class);

    $this->report->process([]);

    expect(fn () => $this->report->reject('Tidak perlu'))->toThrow(LogicException::class)
        ->and(fn () => $this->report->process([]))->toThrow(LogicException::class)
        ->and($this->report->refresh()->status)->toBe(MaintenanceStatus::Diproses);
});

test('an admin starts a repair directly for an available asset', function () {
    $printer = Asset::factory()->create([
        'company_id' => $this->tcm->id,
        'category_id' => $this->laptopCategory->id,
        'status_id' => Status::named(Status::TERSEDIA)->id,
    ]);

    livewire(CreateMaintenance::class)
        ->fillForm(['asset_id' => $printer->id, 'keluhan' => 'Servis rutin', 'tgl_mulai' => now()->toDateString()])
        ->call('create')
        ->assertHasNoFormErrors();

    $repair = Maintenance::query()->where('asset_id', $printer->id)->sole();

    expect($repair->status)->toBe(MaintenanceStatus::Diproses)
        ->and($repair->previousStatus->nama)->toBe(Status::TERSEDIA)
        ->and($printer->refresh()->status->nama)->toBe(Status::DALAM_PERBAIKAN);
});

test('an admin of another category sees neither the repair nor its photo', function () {
    Storage::disk('local')->put('maintenances/layar.jpg', 'foto');

    $this->get(route('filament.admin.maintenances.photo', $this->report))->assertOk();

    $this->actingAs(userWithRole('admin_ga', attributes: ['company_id' => $this->tcm->id]));

    livewire(ListMaintenances::class)->assertCanNotSeeTableRecords([$this->report]);
    $this->get(route('filament.admin.maintenances.photo', $this->report))->assertNotFound();
});
