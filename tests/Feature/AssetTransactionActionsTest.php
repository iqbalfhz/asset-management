<?php

use App\Filament\Resources\Assets\Pages\EditAsset;
use App\Filament\Resources\Assets\Pages\ListAssets;
use App\Models\Asset;
use App\Models\Assignment;
use App\Models\Category;
use App\Models\Company;
use App\Models\Employee;
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
    $this->travelTo(now()->setDate(2026, 11, 11));

    $this->tcm = Company::factory()->create(['kode' => 'TCM']);
    $this->laptop = Category::factory()->create(['role_id' => Role::findByName('admin_it')->id]);
    $this->employee = Employee::factory()->create(['company_id' => $this->tcm->id]);
    $this->actingAs(userWithRole('admin_it', attributes: ['company_id' => $this->tcm->id]));
});

function laptops(Company $company, Category $category, int $count, string $status = Status::TERSEDIA): Collection
{
    return Asset::factory()->count($count)->create([
        'company_id' => $company->id,
        'category_id' => $category->id,
        'status_id' => Status::named($status)->id,
    ]);
}

test('three assets handed over at once to one employee share one BAST and get three history rows', function () {
    $assets = laptops($this->tcm, $this->laptop, 3);

    livewire(ListAssets::class)
        ->selectTableRecords($assets->modelKeys())
        ->callAction(TestAction::make('handOverBulk')->table()->bulk(), data: [
            'target_type' => Employee::class,
            'target_id' => $this->employee->id,
            'tanggal' => '2026-11-11',
            'kondisi' => 'Baik',
        ])
        ->assertHasNoFormErrors()
        ->assertNotified('Serah-terima tersimpan');

    expect(Assignment::where('no_bast', 'BAST/TCM/2026/0001')->count())->toBe(3)
        ->and($assets->fresh()->every(fn (Asset $asset): bool => $asset->status->nama === Status::DIPAKAI))->toBeTrue();
});

test('a refused hand over tells why and changes nothing', function () {
    $assets = laptops($this->tcm, $this->laptop, 1)->merge(laptops($this->tcm, $this->laptop, 1, Status::DISIAPKAN));

    livewire(ListAssets::class)
        ->selectTableRecords($assets->modelKeys())
        ->callAction(TestAction::make('handOverBulk')->table()->bulk(), data: [
            'target_type' => Employee::class,
            'target_id' => $this->employee->id,
            'tanggal' => '2026-11-11',
            'kondisi' => 'Baik',
        ])
        ->assertNotified('Serah-terima dibatalkan');

    expect(Assignment::count())->toBe(0);
});

test('the return action on the edit page returns the asset and refreshes its location', function () {
    $asset = laptops($this->tcm, $this->laptop, 1)->first();
    Assignment::handOver(new Collection([$asset]), $this->employee, ['tanggal' => '2026-11-10', 'kondisi' => 'Baik']);

    livewire(EditAsset::class, ['record' => $asset->id])
        ->callAction('takeBack', data: ['tanggal' => '2026-11-11', 'kondisi' => 'Baik'])
        ->assertNotified('Pengembalian tersimpan');

    expect($asset->refresh()->status->nama)->toBe(Status::TERSEDIA)
        ->and($asset->assigned_id)->toBeNull();
});

test('the hand over and return actions need permission to update assets', function () {
    $asset = laptops($this->tcm, $this->laptop, 1)->first();
    $auditor = userWithRole('auditor', attributes: ['company_id' => $this->tcm->id]);
    Role::findByName('auditor')->givePermissionTo(['ViewAny:Asset', 'view_all_assets']);
    $this->actingAs($auditor);

    livewire(ListAssets::class)
        ->assertCanSeeTableRecords([$asset])
        ->assertActionHidden(TestAction::make('handOver')->table($asset))
        ->assertActionHidden(TestAction::make('handOverBulk')->table()->bulk());
});
