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
        ->callAction(CreateAction::class, data: ['company_id' => $this->company->id, 'nama' => 'Teknologi Informasi'])
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
        ->assertHasActionErrors(['nik' => 'unique']);

    livewire(ManageEmployees::class)
        ->callAction(CreateAction::class, data: ['company_id' => $otherCompany->id, 'nik' => '3271001', 'nama' => 'Ani'])
        ->assertHasNoActionErrors();
});

test('with separation on, admin of company A only sees the organization and locations of company A', function () {
    $otherCompany = Company::factory()->create();

    foreach ([$this->company, $otherCompany] as $company) {
        Department::factory()->create(['company_id' => $company->id]);
        Employee::factory()->create(['company_id' => $company->id]);
        Room::factory()->for(Floor::factory()->for(Building::factory()->for($company)))->create();
    }

    config(['app.company_separation' => true]);
    $this->actingAs(userWithRole('admin_it', attributes: ['company_id' => $this->company->id]));

    expect(Department::pluck('company_id')->all())->toBe([$this->company->id])
        ->and(Employee::pluck('company_id')->all())->toBe([$this->company->id])
        ->and(Building::pluck('company_id')->all())->toBe([$this->company->id])
        ->and(Floor::with('building')->get()->pluck('building.company_id')->all())->toBe([$this->company->id])
        ->and(Room::with('floor.building')->get()->pluck('floor.building.company_id')->all())->toBe([$this->company->id]);

    $this->actingAs(userWithRole('super_admin', attributes: ['company_id' => $this->company->id]));

    expect(Department::count())->toBe(2)
        ->and(Building::count())->toBe(2)
        ->and(Room::count())->toBe(2);
});

test('karyawan and roles without permission cannot open the organization pages', function (string $resource) {
    foreach ([userWithRole('karyawan', 'karyawan'), userWithRole('tamu')] as $user) {
        $this->actingAs($user)->get($resource::getUrl('index'))->assertForbidden();
    }
})->with([DepartmentResource::class, EmployeeResource::class, BuildingResource::class, RoomResource::class]);
