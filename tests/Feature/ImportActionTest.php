<?php

use App\Filament\Imports\AssetImporter;
use App\Filament\Resources\Assets\Pages\ListAssets;
use App\Filament\Resources\Consumables\Pages\ListConsumables;
use App\Filament\Resources\Employees\Pages\ManageEmployees;
use App\Filament\Resources\Licenses\Pages\ListLicenses;
use App\Filament\Resources\Rooms\Pages\ManageRooms;
use App\Models\Role;
use Database\Seeders\RoleSeeder;
use Filament\Actions\ImportAction;
use Filament\Actions\Imports\Models\Import;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Filament::setCurrentPanel('admin');
});

test('an admin can start an asset import from an Excel CSV separated by semicolons', function () {
    $imports = ImportAction::fake();
    $adminIt = userWithRole('admin_it');
    $this->actingAs($adminIt);

    livewire(ListAssets::class)
        ->mountAction('import')
        ->fillForm(['file' => UploadedFile::fake()->createWithContent('aset.csv', "nama;kategori\nLaptop Keuangan;LPT\n")])
        ->fillForm(['columnMap' => ['nama' => 'nama', 'category' => 'kategori']])
        ->callMountedAction()
        ->assertHasNoFormErrors();

    $imports->assertDispatched(AssetImporter::class, fn (Import $import, array $columnMap): bool => $import->user->is($adminIt)
        && $columnMap['category'] === 'kategori');
});

test('the example CSV lists every asset column under its Indonesian header', function () {
    $this->actingAs(userWithRole('admin_it'));

    $page = livewire(ListAssets::class)->callAction(['import', 'downloadExample']);

    expect(base64_decode(data_get($page->effects, 'download.content')))
        ->toContain('perusahaan,nama,kategori,model,status,nomor_seri,gedung,lantai,ruang,supplier,nomor_po,tanggal_beli,harga_beli,penyusutan,garansi_sampai,tanggal_eol,spesifikasi,byod,catatan');
});

test('a file with more than 5,000 rows is refused before importing', function () {
    $imports = ImportAction::fake();
    $this->actingAs(userWithRole('admin_it'));

    livewire(ListAssets::class)
        ->mountAction('import')
        ->fillForm(['file' => UploadedFile::fake()->createWithContent('aset.csv', "nama,kategori\n".str_repeat("Kursi,FRN\n", 5001))])
        ->fillForm(['columnMap' => ['nama' => 'nama', 'category' => 'kategori']])
        ->callMountedAction()
        ->assertNotified();

    $imports->assertNothingDispatched();
});

test('the import button is hidden from roles that may not create the records', function (string $page, string $viewPermission) {
    $auditor = userWithRole('auditor');
    Role::findByName('auditor')->givePermissionTo($viewPermission);
    $this->actingAs($auditor);

    livewire($page)->assertActionHidden('import');
})->with([
    'assets' => [ListAssets::class, 'ViewAny:Asset'],
    'employees' => [ManageEmployees::class, 'ViewAny:Employee'],
    'rooms' => [ManageRooms::class, 'ViewAny:Room'],
    'licenses' => [ListLicenses::class, 'ViewAny:License'],
    'consumables' => [ListConsumables::class, 'ViewAny:Consumable'],
]);

test('the location import also needs permission to create buildings', function () {
    $auditor = userWithRole('auditor');
    Role::findByName('auditor')->givePermissionTo(['ViewAny:Room', 'Create:Room']);
    $this->actingAs($auditor);

    livewire(ManageRooms::class)->assertActionHidden('import');

    Role::findByName('auditor')->givePermissionTo('Create:Building');
    $this->actingAs($auditor->refresh());

    livewire(ManageRooms::class)->assertActionVisible('import');
});
