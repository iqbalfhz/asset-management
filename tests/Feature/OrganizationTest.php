<?php

use App\Filament\Resources\Buildings\BuildingResource;
use App\Filament\Resources\Buildings\Pages\CreateBuilding;
use App\Filament\Resources\Buildings\Pages\EditBuilding;
use App\Filament\Resources\Buildings\RelationManagers\FloorsRelationManager;
use App\Filament\Resources\Departments\DepartmentResource;
use App\Filament\Resources\Departments\Pages\ManageDepartments;
use App\Filament\Resources\Employees\EmployeeResource;
use App\Filament\Resources\Employees\Pages\ManageEmployees;
use App\Filament\Resources\Rooms\Pages\ManageRooms;
use App\Filament\Resources\Rooms\RoomResource;
use App\Models\Building;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Floor;
use App\Models\Room;
use App\Models\Setting;
use Database\Seeders\RoleSeeder;
use Filament\Actions\CreateAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Filament::setCurrentPanel('admin');
    $this->company = Company::factory()->create();
});

test('admin IT can record a department, an employee, a building with its floor, and a room', function () {
    $this->actingAs(userWithRole('admin_it', attributes: ['company_id' => $this->company->id]));

    livewire(ManageDepartments::class)
        ->callAction(CreateAction::class, data: ['nama' => 'Teknologi Informasi'])
        ->assertHasNoActionErrors();
    $department = Department::firstWhere('nama', 'Teknologi Informasi');

    livewire(ManageEmployees::class)
        ->callAction(CreateAction::class, data: [
            'company_id' => $this->company->id,
            'nik' => '3271001',
            'nama' => 'Budi Santoso',
            'jabatan' => 'Staf IT',
            'department_id' => $department->id,
        ])
        ->assertHasNoActionErrors();
    $budi = Employee::firstWhere('nik', '3271001');

    livewire(CreateBuilding::class)
        ->fillForm(['company_id' => $this->company->id, 'nama' => 'Gedung A', 'manager_id' => $budi->id])
        ->call('create')
        ->assertHasNoFormErrors();
    $gedungA = Building::firstWhere('nama', 'Gedung A');

    livewire(FloorsRelationManager::class, ['ownerRecord' => $gedungA, 'pageClass' => EditBuilding::class])
        ->callAction(TestAction::make('create')->table(), data: ['nama' => 'Lantai 1'])
        ->assertHasNoActionErrors();
    $lantai1 = Floor::firstWhere('nama', 'Lantai 1');

    livewire(ManageRooms::class)
        ->callAction(CreateAction::class, data: ['floor_id' => $lantai1->id, 'nama' => 'Ruang Server', 'manager_id' => $budi->id])
        ->assertHasNoActionErrors();

    expect($budi->department->is($department))->toBeTrue()
        ->and($gedungA->manager->is($budi))->toBeTrue()
        ->and($lantai1->building->is($gedungA))->toBeTrue()
        ->and(Room::firstWhere('nama', 'Ruang Server')->floor->is($lantai1))->toBeTrue();
});

test('the same NIK is rejected within a company but allowed in another company', function () {
    $this->actingAs(userWithRole('super_admin', attributes: ['company_id' => $this->company->id]));
    $otherCompany = Company::factory()->create();
    Employee::factory()->create(['company_id' => $this->company->id, 'nik' => '3271001']);

    livewire(ManageEmployees::class)
        ->callAction(CreateAction::class, data: ['company_id' => $this->company->id, 'nik' => '3271001', 'nama' => 'Ani'])
        ->assertHasActionErrors(['nik' => ['NIK sudah dipakai.']]);

    livewire(ManageEmployees::class)
        ->callAction(CreateAction::class, data: ['company_id' => $otherCompany->id, 'nik' => '3271001', 'nama' => 'Ani'])
        ->assertHasNoActionErrors();
});

test('an inactive company is not offered for new data, but its existing records still save', function () {
    $this->actingAs(userWithRole('super_admin', attributes: ['company_id' => $this->company->id]));
    $this->company->update(['is_active' => false]);
    $existing = Employee::factory()->create(['company_id' => $this->company->id, 'nik' => '3271001']);

    // Perusahaan akun yang nonaktif tidak dipakai sebagai isian awal.
    livewire(ManageEmployees::class)
        ->callAction(CreateAction::class, data: ['nik' => '3271002', 'nama' => 'Dwi'])
        ->assertHasActionErrors(['company_id' => 'required']);

    livewire(ManageEmployees::class)
        ->callAction(CreateAction::class, data: ['company_id' => $this->company->id, 'nik' => '3271002', 'nama' => 'Dwi'])
        ->assertHasActionErrors(['company_id']);

    livewire(ManageEmployees::class)
        ->callAction(TestAction::make('edit')->table($existing), data: ['nama' => 'Dwi Lama'])
        ->assertHasNoActionErrors();

    expect($existing->refresh()->nama)->toBe('Dwi Lama')
        ->and($existing->company_id)->toBe($this->company->id)
        ->and(Employee::count())->toBe(1);
});

test('one department holds employees of several companies and its name is used once', function () {
    $this->actingAs(userWithRole('super_admin', attributes: ['company_id' => $this->company->id]));
    $otherCompany = Company::factory()->create();
    $collection = Department::factory()->create(['nama' => 'Collection']);

    foreach ([$this->company, $otherCompany] as $company) {
        livewire(ManageEmployees::class)
            ->callAction(CreateAction::class, data: ['company_id' => $company->id, 'nik' => '3271001', 'nama' => 'Dwi', 'department_id' => $collection->id])
            ->assertHasNoActionErrors();
    }

    livewire(ManageDepartments::class)
        ->callAction(CreateAction::class, data: ['nama' => 'Collection'])
        ->assertHasActionErrors(['nama' => 'unique']);

    expect(Employee::query()->where('department_id', $collection->id)->pluck('company_id')->sort()->values()->all())
        ->toBe([$this->company->id, $otherCompany->id]);
});

test('with separation on, admin of company A only sees the employees and locations of company A, while departments stay shared', function () {
    $otherCompany = Company::factory()->create();
    Department::factory()->count(2)->create();

    foreach ([$this->company, $otherCompany] as $company) {
        Employee::factory()->create(['company_id' => $company->id]);
        Room::factory()->for(Floor::factory()->for(Building::factory()->for($company)))->create();
    }

    Setting::put('company_separation', true);
    $this->actingAs(userWithRole('admin_it', attributes: ['company_id' => $this->company->id]));

    expect(Department::count())->toBe(2)
        ->and(Employee::pluck('company_id')->all())->toBe([$this->company->id])
        ->and(Building::pluck('company_id')->all())->toBe([$this->company->id])
        ->and(Floor::with('building')->get()->pluck('building.company_id')->all())->toBe([$this->company->id])
        ->and(Room::with('floor.building')->get()->pluck('floor.building.company_id')->all())->toBe([$this->company->id]);

    $this->actingAs(userWithRole('super_admin', attributes: ['company_id' => $this->company->id]));

    expect(Employee::count())->toBe(2)
        ->and(Building::count())->toBe(2)
        ->and(Room::count())->toBe(2);
});

test('employees are listed by name and can be sorted the other way', function () {
    $this->actingAs(userWithRole('super_admin', attributes: ['company_id' => $this->company->id]));
    $budi = Employee::factory()->create(['company_id' => $this->company->id, 'nama' => 'Budi', 'nik' => '1']);
    $ani = Employee::factory()->create(['company_id' => $this->company->id, 'nama' => 'Ani', 'nik' => '2']);

    livewire(ManageEmployees::class)
        ->assertCanSeeTableRecords([$ani, $budi], inOrder: true)
        ->sortTable('nama', 'desc')
        ->assertCanSeeTableRecords([$budi, $ani], inOrder: true);
});

test('karyawan and roles without permission cannot open the organization pages', function (string $resource) {
    foreach ([userWithRole('karyawan', 'karyawan'), userWithRole('tamu')] as $user) {
        $this->actingAs($user)->get($resource::getUrl('index'))->assertForbidden();
    }
})->with([DepartmentResource::class, EmployeeResource::class, BuildingResource::class, RoomResource::class]);
