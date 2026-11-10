<?php

use App\Filament\Resources\AssetModels\AssetModelResource;
use App\Filament\Resources\AssetModels\Pages\ManageAssetModels;
use App\Models\AssetModel;
use App\Models\Category;
use App\Models\Depreciation;
use App\Models\Manufacturer;
use Database\Seeders\RoleSeeder;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Filament::setCurrentPanel('admin');
});

test('admin IT can create an asset model linked to manufacturer, category and depreciation class', function () {
    $this->actingAs(userWithRole('admin_it'));
    $lenovo = Manufacturer::factory()->create(['nama' => 'Lenovo']);
    $laptop = Category::factory()->create(['nama' => 'Laptop']);
    $empatTahun = Depreciation::factory()->create(['jumlah_bulan' => 48]);

    livewire(ManageAssetModels::class)
        ->callAction(CreateAction::class, data: [
            'manufacturer_id' => $lenovo->id,
            'nama' => 'ThinkPad T14',
            'nomor_model' => '21HD',
            'category_id' => $laptop->id,
            'depreciation_id' => $empatTahun->id,
        ])
        ->assertHasNoActionErrors();

    $thinkpad = AssetModel::firstWhere('nama', 'ThinkPad T14');

    expect($thinkpad->manufacturer->is($lenovo))->toBeTrue()
        ->and($thinkpad->category->is($laptop))->toBeTrue()
        ->and($thinkpad->depreciation->is($empatTahun))->toBeTrue();
});

test('an asset model needs a manufacturer and a category', function () {
    $this->actingAs(userWithRole('admin_it'));

    livewire(ManageAssetModels::class)
        ->callAction(CreateAction::class, data: ['nama' => 'ThinkPad T14'])
        ->assertHasActionErrors(['manufacturer_id' => 'required', 'category_id' => 'required']);

    expect(AssetModel::count())->toBe(0);
});

test('the same model name is rejected within one manufacturer', function () {
    $this->actingAs(userWithRole('admin_it'));
    $existing = AssetModel::factory()->create(['nama' => 'ThinkPad T14']);

    livewire(ManageAssetModels::class)
        ->callAction(CreateAction::class, data: [
            'manufacturer_id' => $existing->manufacturer_id,
            'nama' => 'ThinkPad T14',
            'category_id' => $existing->category_id,
        ])
        ->assertHasActionErrors(['nama' => 'unique']);
});

test('karyawan and roles without permission cannot open the asset model page', function () {
    foreach ([userWithRole('karyawan', 'karyawan'), userWithRole('tamu')] as $user) {
        $this->actingAs($user)->get(AssetModelResource::getUrl('index'))->assertForbidden();
    }
});
