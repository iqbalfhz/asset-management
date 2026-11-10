<?php

use App\Filament\Resources\Depreciations\DepreciationResource;
use App\Filament\Resources\Depreciations\Pages\ManageDepreciations;
use App\Filament\Resources\Manufacturers\ManufacturerResource;
use App\Filament\Resources\Manufacturers\Pages\ManageManufacturers;
use App\Filament\Resources\Suppliers\Pages\ManageSuppliers;
use App\Filament\Resources\Suppliers\SupplierResource;
use App\Models\Depreciation;
use App\Models\Manufacturer;
use App\Models\Supplier;
use Database\Seeders\RoleSeeder;
use Filament\Actions\CreateAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Filament::setCurrentPanel('admin');
});

dataset('simple master data', [
    'supplier' => [ManageSuppliers::class, Supplier::class, ['nama' => 'PT Sumber Makmur', 'kontak' => '021-5550123']],
    'produsen' => [ManageManufacturers::class, Manufacturer::class, ['nama' => 'Lenovo', 'website' => 'https://www.lenovo.com']],
    'kelas penyusutan' => [ManageDepreciations::class, Depreciation::class, ['nama' => 'Laptop 4 tahun', 'jumlah_bulan' => 48]],
]);

test('admin IT can create, edit and delete', function (string $page, string $model, array $data) {
    $this->actingAs(userWithRole('admin_it'));

    livewire($page)
        ->callAction(CreateAction::class, data: $data)
        ->assertHasNoActionErrors();

    $record = $model::firstWhere('nama', $data['nama']);
    expect($record)->not->toBeNull();

    livewire($page)
        ->callAction(TestAction::make('edit')->table($record), data: ['nama' => "{$data['nama']} Baru"])
        ->assertHasNoActionErrors();

    expect($record->refresh()->nama)->toBe("{$data['nama']} Baru");

    livewire($page)->callAction(TestAction::make('delete')->table($record));

    expect($model::find($record->id))->toBeNull();
})->with('simple master data');

test('a duplicate name is rejected', function (string $page, string $model, array $data) {
    $this->actingAs(userWithRole('admin_it'));
    $model::factory()->create(['nama' => $data['nama']]);

    livewire($page)
        ->callAction(CreateAction::class, data: $data)
        ->assertHasActionErrors(['nama' => 'unique']);
})->with('simple master data');

test('karyawan and roles without permission cannot open the page', function (string $resource) {
    foreach ([userWithRole('karyawan', 'karyawan'), userWithRole('tamu')] as $user) {
        $this->actingAs($user)->get($resource::getUrl('index'))->assertForbidden();
    }
})->with([SupplierResource::class, ManufacturerResource::class, DepreciationResource::class]);
