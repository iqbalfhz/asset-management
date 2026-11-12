<?php

use App\Filament\Resources\Employees\EmployeeResource;
use App\Filament\Resources\Employees\Pages\ManageEmployees;
use App\Filament\Resources\Employees\Pages\ViewEmployee;
use App\Filament\Resources\Employees\RelationManagers\AssetsRelationManager;
use App\Filament\Resources\Employees\RelationManagers\ConsumableTransactionsRelationManager;
use App\Filament\Resources\Employees\RelationManagers\LicenseSeatsRelationManager;
use App\Models\Asset;
use App\Models\Assignment;
use App\Models\Category;
use App\Models\Company;
use App\Models\Consumable;
use App\Models\Employee;
use App\Models\License;
use App\Models\Role;
use App\Models\Status;
use Database\Seeders\RoleSeeder;
use Database\Seeders\StatusSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Collection;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed([RoleSeeder::class, StatusSeeder::class]);
    Filament::setCurrentPanel('admin');

    $this->tcm = Company::factory()->create();
    $this->adminIt = userWithRole('admin_it', attributes: ['company_id' => $this->tcm->id]);
    $this->budi = Employee::factory()->create(['company_id' => $this->tcm->id, 'nama' => 'Budi Santoso']);
    $this->actingAs($this->adminIt);

    $assetOf = fn (string $role): Asset => Asset::factory()->create([
        'company_id' => $this->tcm->id,
        'category_id' => Category::factory()->create(['role_id' => Role::findByName($role)->id])->id,
        'status_id' => Status::named(Status::TERSEDIA)->id,
    ]);
    $this->laptop = $assetOf('admin_it');
    $this->chair = $assetOf('admin_ga');
    Assignment::handOver(new Collection([$this->laptop]), $this->budi, ['tanggal' => now()->toDateString(), 'kondisi' => 'Baik']);
    // Kursi kategori GA diserahkan tanpa scope, seperti oleh admin GA.
    Asset::withoutGlobalScopes()->whereKey($this->chair->id)->update(['assigned_type' => $this->budi->getMorphClass(), 'assigned_id' => $this->budi->id]);

    $this->seat = License::factory()->create(['company_id' => $this->tcm->id])->assignSeat($this->budi);
    $this->toner = Consumable::factory()->create(['company_id' => $this->tcm->id]);
    $this->toner->receive(5);
    $this->issued = $this->toner->issue(2, $this->budi);
});

test('the employee page shows the assets, license seats and consumables the admin manages', function () {
    $this->get(EmployeeResource::getUrl('view', ['record' => $this->budi]))->assertOk()->assertSee('Budi Santoso');

    livewire(AssetsRelationManager::class, ['ownerRecord' => $this->budi, 'pageClass' => ViewEmployee::class])
        ->assertCanSeeTableRecords([$this->laptop])
        ->assertCanNotSeeTableRecords([$this->chair]);

    livewire(LicenseSeatsRelationManager::class, ['ownerRecord' => $this->budi, 'pageClass' => ViewEmployee::class])
        ->assertCanSeeTableRecords([$this->seat]);

    livewire(ConsumableTransactionsRelationManager::class, ['ownerRecord' => $this->budi, 'pageClass' => ViewEmployee::class])
        ->assertCanSeeTableRecords([$this->issued]);
});

test('a released seat leaves the list, and the employee table links to the page', function () {
    $this->seat->release();

    livewire(LicenseSeatsRelationManager::class, ['ownerRecord' => $this->budi, 'pageClass' => ViewEmployee::class])
        ->assertCanNotSeeTableRecords([$this->seat]);

    livewire(ManageEmployees::class)
        ->assertActionHasUrl(TestAction::make('view')->table($this->budi), EmployeeResource::getUrl('view', ['record' => $this->budi]));
});

test('an employee account cannot open the page', function () {
    $this->actingAs(userWithRole('karyawan', 'karyawan', ['company_id' => $this->tcm->id]));

    $this->get(EmployeeResource::getUrl('view', ['record' => $this->budi]))->assertForbidden();
});
