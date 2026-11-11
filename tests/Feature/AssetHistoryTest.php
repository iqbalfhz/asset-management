<?php

use App\Filament\Resources\Assets\Pages\EditAsset;
use App\Filament\Resources\Assets\RelationManagers\ActivitiesRelationManager;
use App\Filament\Resources\Assets\RelationManagers\AssignmentsRelationManager;
use App\Filament\Resources\Assignments\Pages\ListAssignments;
use App\Models\Asset;
use App\Models\Assignment;
use App\Models\Building;
use App\Models\Category;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Floor;
use App\Models\Role;
use App\Models\Room;
use App\Models\Status;
use Database\Seeders\RoleSeeder;
use Database\Seeders\StatusSeeder;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Collection;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed([RoleSeeder::class, StatusSeeder::class]);
    Filament::setCurrentPanel('admin');

    $this->tcm = Company::factory()->create();
    $this->laptop = Category::factory()->create(['role_id' => Role::findByName('admin_it')->id]);
    $this->actingAs(userWithRole('admin_it', attributes: ['company_id' => $this->tcm->id]));
    $this->details = ['tanggal' => now()->toDateString(), 'kondisi' => 'Baik'];
});

function laptopFor(Company $company, Category $category, string $nama = 'Laptop'): Asset
{
    return Asset::factory()->create([
        'company_id' => $company->id,
        'category_id' => $category->id,
        'status_id' => Status::named(Status::TERSEDIA)->id,
        'nama' => $nama,
    ]);
}

test('the asset page lists its hand overs and returns', function () {
    $asset = laptopFor($this->tcm, $this->laptop);
    Assignment::handOver(new Collection([$asset]), Employee::factory()->create(['company_id' => $this->tcm->id]), $this->details);
    Assignment::takeBack(new Collection([$asset->refresh()]), $this->details);

    livewire(AssignmentsRelationManager::class, ['ownerRecord' => $asset, 'pageClass' => EditAsset::class])
        ->assertCanSeeTableRecords($asset->assignments)
        ->assertCountTableRecords(2);
});

test('the change log shows each changed column with its old and new value', function () {
    $asset = laptopFor($this->tcm, $this->laptop, 'Laptop Lama');
    $asset->update(['nama' => 'Laptop Baru']);

    livewire(ActivitiesRelationManager::class, ['ownerRecord' => $asset, 'pageClass' => EditAsset::class])
        ->assertSee('nama: Laptop Lama → Laptop Baru');
});

test('the hand over list can show only those still waiting for acceptance', function () {
    $toEmployee = laptopFor($this->tcm, $this->laptop);
    $toRoom = laptopFor($this->tcm, $this->laptop);
    Assignment::handOver(new Collection([$toEmployee]), Employee::factory()->create(['company_id' => $this->tcm->id]), $this->details);
    $room = Room::factory()->for(Floor::factory()->for(Building::factory()->state(['company_id' => $this->tcm->id])))->create();
    Assignment::handOver(new Collection([$toRoom]), $room, $this->details);

    livewire(ListAssignments::class)
        ->filterTable('status', 'menunggu')
        ->assertCanSeeTableRecords($toEmployee->assignments)
        ->assertCanNotSeeTableRecords($toRoom->assignments);
});

test('an admin does not see hand overs of assets in categories they do not manage', function () {
    $asset = laptopFor($this->tcm, $this->laptop);
    Assignment::handOver(new Collection([$asset]), Employee::factory()->create(['company_id' => $this->tcm->id]), $this->details);
    $this->actingAs(userWithRole('admin_ga', attributes: ['company_id' => $this->tcm->id]));

    livewire(ListAssignments::class)->assertCanNotSeeTableRecords(Assignment::withoutGlobalScopes()->get());
});
