<?php

use App\Filament\Resources\Assets\Pages\EditAsset;
use App\Filament\Resources\Assets\RelationManagers\ComponentsRelationManager;
use App\Filament\Resources\Components\ComponentResource;
use App\Filament\Resources\Components\Pages\ListComponents;
use App\Filament\Resources\Components\Pages\ViewComponent;
use App\Filament\Resources\Components\RelationManagers\InstallationsRelationManager;
use App\Models\Category;
use App\Models\Company;
use App\Models\Component;
use App\Models\ComponentInstallation;
use App\Models\Role;
use App\Models\Status;
use Database\Seeders\RoleSeeder;
use Database\Seeders\StatusSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed([RoleSeeder::class, StatusSeeder::class]);
    Filament::setCurrentPanel('admin');

    $this->tcm = Company::factory()->create();
    $this->adminIt = userWithRole('admin_it', attributes: ['company_id' => $this->tcm->id]);
    $this->ram = Component::factory()->create(['company_id' => $this->tcm->id, 'nama' => 'RAM DDR4 8GB', 'jumlah' => 4]);
    $this->laptop = availableAsset($this->tcm, Category::factory()->create(['role_id' => Role::findByName('admin_it')->id]));

    $this->actingAs($this->adminIt);
});

test('installing and removing moves what is available, and the history shows on the asset', function () {
    livewire(InstallationsRelationManager::class, ['ownerRecord' => $this->ram, 'pageClass' => ViewComponent::class])
        ->callAction(TestAction::make('install')->table(), data: ['asset_id' => $this->laptop->id, 'jumlah' => 2])
        ->assertNotified('Komponen dipasang');

    $installation = ComponentInstallation::query()->sole();

    expect($this->ram->availableQuantity())->toBe(2)
        ->and($installation->asset_id)->toBe($this->laptop->id)
        ->and($installation->dipasang_oleh)->toBe($this->adminIt->id);

    livewire(ComponentsRelationManager::class, ['ownerRecord' => $this->laptop, 'pageClass' => EditAsset::class])
        ->assertCanSeeTableRecords([$installation])
        ->callAction(TestAction::make('release')->table($installation))
        ->assertNotified('Komponen dilepas');

    expect($this->ram->availableQuantity())->toBe(4)
        ->and($installation->refresh()->dilepas_oleh)->toBe($this->adminIt->id);

    // Riwayat tetap ada setelah dilepas, cukup matikan filter "masih dipakai".
    livewire(ComponentsRelationManager::class, ['ownerRecord' => $this->laptop, 'pageClass' => EditAsset::class])
        ->assertCanNotSeeTableRecords([$installation])
        ->filterTable('aktif', false)
        ->assertCanSeeTableRecords([$installation]);
});

test('an install above what is available, into a written-off asset, or into another company is refused', function () {
    $this->ram->install($this->laptop, 3);
    $writtenOff = availableAsset($this->tcm, $this->laptop->category);
    $writtenOff->transitionTo(Status::named(Status::DIHAPUSKAN));
    $outsider = availableAsset(Company::factory()->create(), $this->laptop->category);

    expect(fn () => $this->ram->install($this->laptop, 2))->toThrow(LogicException::class, 'tinggal 1 yang tersedia')
        ->and(fn () => $this->ram->install($writtenOff->refresh(), 1))->toThrow(LogicException::class, 'sudah dihapuskan')
        ->and(fn () => $this->ram->install($outsider, 1))->toThrow(LogicException::class, 'perusahaan lain')
        ->and($this->ram->availableQuantity())->toBe(1);
});

test('an admin of another role does not see the component, and karyawan cannot open the menu', function () {
    $this->actingAs(userWithRole('admin_ga', attributes: ['company_id' => $this->tcm->id]));

    livewire(ListComponents::class)->assertCanNotSeeTableRecords([$this->ram]);

    $this->actingAs(userWithRole('karyawan', 'karyawan'))
        ->get(ComponentResource::getUrl())
        ->assertForbidden();
});

test('the component list shows what is available', function () {
    $this->ram->install($this->laptop, 1);

    $this->get(ComponentResource::getUrl())->assertOk()->assertSee('3 dari 4');
});
