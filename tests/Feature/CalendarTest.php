<?php

use App\Enums\MaintenanceStatus;
use App\Filament\Pages\Calendar;
use App\Filament\Resources\Assets\AssetResource;
use App\Filament\Resources\Licenses\LicenseResource;
use App\Models\Assignment;
use App\Models\Category;
use App\Models\Company;
use App\Models\Employee;
use App\Models\License;
use App\Models\Maintenance;
use App\Models\Role;
use App\Models\Status;
use Database\Seeders\RoleSeeder;
use Database\Seeders\StatusSeeder;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Collection;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed([RoleSeeder::class, StatusSeeder::class]);
    $this->travelTo(now()->setDate(2026, 11, 11)->setTime(9, 0));
    Filament::setCurrentPanel('admin');

    $this->tcm = Company::factory()->create(['kode' => 'TCM']);
    $this->it = Category::factory()->create(['role_id' => Role::findByName('admin_it')->id]);
    $this->adminIt = userWithRole('admin_it', attributes: ['company_id' => $this->tcm->id]);
    $this->actingAs($this->adminIt);

    $this->loan = availableAsset($this->tcm, $this->it);
    Assignment::handOver(new Collection([$this->loan]), Employee::factory()->create(['company_id' => $this->tcm->id, 'nama' => 'Rina']), [
        'tanggal' => '2026-11-02',
        'kondisi' => 'Baik',
        'expected_return_at' => '2026-11-20',
    ]);

    $this->repair = Maintenance::factory()->create([
        'asset_id' => availableAsset($this->tcm, $this->it)->id,
        'status' => MaintenanceStatus::Diproses,
        'tgl_mulai' => '2026-11-05',
    ]);
    $this->audit = availableAsset($this->tcm, $this->it, ['next_audit_date' => '2026-11-25']);
    $this->license = License::factory()->create(['company_id' => $this->tcm->id, 'nama_software' => 'AutoCAD LT', 'tgl_habis' => '2026-11-30']);
});

test('a month shows return dates, repairs, audits and license expiries, each linking to its record', function () {
    livewire(Calendar::class)
        ->assertSee('November 2026')
        ->assertSee([$this->loan->kode, 'Dipinjam Rina', $this->repair->asset->kode, $this->audit->kode, 'AutoCAD LT'])
        ->assertSeeHtml(AssetResource::getUrl('view', ['record' => $this->audit]))
        ->assertSeeHtml(LicenseResource::getUrl('view', ['record' => $this->license]));
});

test('returned loans, rejected repairs and lost assets leave the calendar', function () {
    Assignment::takeBack(new Collection([$this->loan->refresh()]), ['tanggal' => '2026-11-09', 'kondisi' => 'Baik']);
    $this->repair->update(['status' => MaintenanceStatus::Ditolak]);
    $lost = availableAsset($this->tcm, $this->it, ['next_audit_date' => '2026-11-26']);
    $lost->transitionTo(Status::named(Status::HILANG));

    livewire(Calendar::class)
        ->assertDontSee($this->loan->kode)
        ->assertDontSee($this->repair->asset->kode)
        ->assertDontSee($lost->kode)
        ->assertSee($this->audit->kode);
});

test('an admin sees only dates of what they manage and are allowed to view', function () {
    $ga = Category::factory()->create(['role_id' => Role::findByName('admin_ga')->id]);
    $desk = availableAsset($this->tcm, $ga, ['next_audit_date' => '2026-11-12']);
    Role::findByName('admin_it')->revokePermissionTo('ViewAny:License');

    livewire(Calendar::class)
        ->assertSee($this->audit->kode)
        ->assertDontSee($desk->kode)
        ->assertDontSee('AutoCAD LT');
});

test('the buttons move between months, and an unknown month opens this month', function () {
    $december = availableAsset($this->tcm, $this->it, ['next_audit_date' => '2026-12-15']);

    livewire(Calendar::class)
        ->assertDontSee($december->kode)
        ->callAction('next')
        ->assertSet('bulan', '2026-12')
        ->assertSee(['Desember 2026', $december->kode])
        ->callAction('current')
        ->assertSee('November 2026');

    livewire(Calendar::class, ['bulan' => '2026-13'])->assertSee('November 2026');
});
