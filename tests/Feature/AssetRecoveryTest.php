<?php

use App\Enums\DisposalMethod;
use App\Filament\Resources\Assets\Pages\ViewAsset;
use App\Models\Asset;
use App\Models\Building;
use App\Models\Category;
use App\Models\Company;
use App\Models\Disposal;
use App\Models\Employee;
use App\Models\Floor;
use App\Models\Role;
use App\Models\Room;
use App\Models\Status;
use Database\Seeders\RoleSeeder;
use Database\Seeders\StatusSeeder;
use Filament\Facades\Filament;
use Spatie\Activitylog\Models\Activity;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed([RoleSeeder::class, StatusSeeder::class]);
    Filament::setCurrentPanel('admin');

    $this->tcm = Company::factory()->create();
    $floor = Floor::factory()->create(['building_id' => Building::factory()->create(['company_id' => $this->tcm->id])->id]);
    $this->warehouse = Room::factory()->create(['floor_id' => $floor->id]);
    $this->adminIt = userWithRole('admin_it', attributes: ['company_id' => $this->tcm->id]);
    $this->employee = Employee::factory()->create(['company_id' => $this->tcm->id]);

    // Laptop yang hilang saat masih dipegang karyawan.
    $this->laptop = Asset::factory()->create([
        'company_id' => $this->tcm->id,
        'category_id' => Category::factory()->create(['role_id' => Role::findByName('admin_it')->id])->id,
        'status_id' => Status::named(Status::TERSEDIA)->id,
    ]);
    $this->laptop->transitionTo(Status::named(Status::HILANG), ['assigned_type' => $this->employee->getMorphClass(), 'assigned_id' => $this->employee->id]);

    $this->actingAs($this->adminIt);
});

test('a lost asset found again becomes available in the chosen room without its old holder, and the reason is logged', function () {
    livewire(ViewAsset::class, ['record' => $this->laptop->id])
        ->callAction('recover', data: ['room_id' => $this->warehouse->id, 'alasan' => 'Dikembalikan mantan karyawan'])
        ->assertNotified('Aset kembali Tersedia')
        ->assertActionHidden('recover');

    $this->laptop->refresh();

    expect($this->laptop->status->nama)->toBe(Status::TERSEDIA)
        ->and($this->laptop->assigned_type)->toBeNull()
        ->and($this->laptop->room_id)->toBe($this->warehouse->id)
        ->and(Activity::query()->where('event', 'ditemukan_kembali')->sole()->properties['alasan'])->toBe('Dikembalikan mantan karyawan');
});

test('recovery is refused for an asset that is not lost, without a reason, with a pending disposal, or to a room of another company', function () {
    $otherRoom = Room::factory()->create();

    expect(fn () => $this->laptop->recover($this->warehouse, ''))->toThrow(LogicException::class, 'wajib')
        ->and(fn () => $this->laptop->recover($otherRoom, 'Ketemu'))->toThrow(LogicException::class, 'perusahaan lain');

    Disposal::submit($this->laptop, ['alasan' => 'Hilang saat dinas', 'metode' => DisposalMethod::Hilang]);

    expect(fn () => $this->laptop->recover($this->warehouse, 'Ketemu'))->toThrow(LogicException::class, 'ajuan penghapusan');

    $available = Asset::factory()->create(['company_id' => $this->tcm->id, 'category_id' => $this->laptop->category_id, 'status_id' => Status::named(Status::TERSEDIA)->id]);

    expect(fn () => $available->recover($this->warehouse, 'Ketemu'))->toThrow(LogicException::class, 'Hanya aset Hilang')
        ->and($this->laptop->refresh()->status->nama)->toBe(Status::HILANG);

    livewire(ViewAsset::class, ['record' => $available->id])->assertActionHidden('recover');
});
