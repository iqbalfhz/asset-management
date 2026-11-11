<?php

use App\Filament\Imports\AssetImporter;
use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Building;
use App\Models\Category;
use App\Models\Company;
use App\Models\Floor;
use App\Models\Manufacturer;
use App\Models\Role;
use App\Models\Room;
use App\Models\Setting;
use App\Models\Status;
use App\Models\Supplier;
use Database\Seeders\RoleSeeder;
use Database\Seeders\StatusSeeder;

beforeEach(function () {
    $this->seed([RoleSeeder::class, StatusSeeder::class]);
    $this->travelTo(now()->setDate(2026, 11, 11));

    $this->tcm = Company::factory()->create(['kode' => 'TCM']);
    $this->laptop = Category::factory()->create([
        'nama' => 'Laptop',
        'kode' => 'LPT',
        'role_id' => Role::findByName('admin_it')->id,
        'template_spesifikasi' => ['RAM', 'SSD'],
    ]);

    $this->actingAs(userWithRole('admin_it', attributes: ['company_id' => $this->tcm->id]));
});

function financeRoom(Company $company): Room
{
    $building = Building::factory()->create(['company_id' => $company->id, 'nama' => 'Gedung A']);
    $floor = Floor::factory()->create(['building_id' => $building->id, 'nama' => 'Lantai 2']);

    return Room::factory()->create(['floor_id' => $floor->id, 'nama' => 'Ruang Keuangan']);
}

test('a complete row becomes an asset with a generated code and every looked-up value', function () {
    $room = financeRoom($this->tcm);
    $model = AssetModel::factory()->create([
        'manufacturer_id' => Manufacturer::factory()->create(['nama' => 'Dell'])->id,
        'category_id' => $this->laptop->id,
        'nama' => 'Latitude 5440',
        'eol_bulan' => 60,
    ]);
    $supplier = Supplier::factory()->create(['nama' => 'PT Sumber Komputer']);

    $asset = AssetImporter::test()->import([
        'company' => 'tcm',
        'nama' => 'Laptop Keuangan',
        'category' => 'lpt',
        'assetModel' => 'Dell Latitude 5440',
        'status' => 'tersedia',
        'no_seri' => 'SN-001',
        'gedung' => 'Gedung A',
        'lantai' => 'Lantai 2',
        'ruang' => 'Ruang Keuangan',
        'supplier' => 'PT Sumber Komputer',
        'order_number' => 'PO-2026-014',
        'tgl_beli' => '31/01/2026',
        'harga_beli' => 'Rp 12.500.000',
        'garansi_sampai' => '2029-01-31',
        'spesifikasi' => 'RAM: 16 GB; Warna: Hitam',
        'is_byod' => 'tidak',
        'notes' => 'Kode lama IT-001',
    ])->assertImported()->getRecord()->refresh();

    expect($asset->kode)->toBe('TCM-LPT-2026-00001')
        ->and($asset->status->nama)->toBe(Status::TERSEDIA)
        ->and($asset->asset_model_id)->toBe($model->id)
        ->and($asset->room_id)->toBe($room->id)
        ->and($asset->default_room_id)->toBe($room->id)
        ->and($asset->supplier_id)->toBe($supplier->id)
        ->and($asset->tgl_beli->toDateString())->toBe('2026-01-31')
        ->and($asset->eol_date->toDateString())->toBe('2031-01-31')
        ->and($asset->garansi_sampai->toDateString())->toBe('2029-01-31')
        ->and($asset->harga_beli)->toBe(12500000)
        ->and($asset->spesifikasi)->toBe(['RAM' => '16 GB', 'SSD' => '', 'Warna' => 'Hitam'])
        ->and($asset->is_byod)->toBeFalse()
        ->and($asset->notes)->toBe('Kode lama IT-001');
});

test('blank optional cells fall back to Disiapkan and the company of the importing account', function () {
    $asset = AssetImporter::test()->import(['nama' => 'Laptop Cadangan', 'category' => 'Laptop'])
        ->assertImported()
        ->getRecord();

    expect($asset->company_id)->toBe($this->tcm->id)
        ->and($asset->status->nama)->toBe(Status::DISIAPKAN)
        ->and($asset->room_id)->toBeNull();
});

test('a category managed by another admin fails the row', function () {
    Category::factory()->create(['nama' => 'Furnitur', 'kode' => 'FRN', 'role_id' => Role::findByName('admin_ga')->id]);

    AssetImporter::test()->import(['nama' => 'Meja', 'category' => 'Furnitur'])
        ->assertHasErrors(['category' => 'Kolom kategori yang dipilih tidak valid.']);

    expect(Asset::count())->toBe(0);
});

test('a starting status other than Disiapkan or Tersedia fails the row', function () {
    AssetImporter::test()->import(['nama' => 'Laptop', 'category' => 'LPT', 'status' => 'Dipakai'])
        ->assertHasErrors(['status']);

    expect(Asset::count())->toBe(0);
});

test('a location that does not exist fails the row and names the location', function () {
    financeRoom($this->tcm);

    AssetImporter::test()->import(['nama' => 'Laptop', 'category' => 'LPT', 'gedung' => 'Gedung A', 'lantai' => 'Lantai 9', 'ruang' => 'Ruang Keuangan'])
        ->assertHasRowFailure('Lokasi Gedung A — Lantai 9 — Ruang Keuangan tidak ditemukan di perusahaan ini.');

    expect(Asset::count())->toBe(0);
});

test('a model that requires a serial number fails the row without one', function () {
    AssetModel::factory()->create(['category_id' => $this->laptop->id, 'nama' => 'ThinkPad T14', 'wajib_nomor_seri' => true]);

    AssetImporter::test()->import(['nama' => 'Laptop', 'category' => 'LPT', 'assetModel' => 'ThinkPad T14'])
        ->assertHasRowFailure('Nomor seri wajib diisi untuk model ThinkPad T14.');

    expect(Asset::count())->toBe(0);
});

test('an unreadable value fails the row', function (string $column, string $value) {
    AssetImporter::test()->import(['nama' => 'Laptop', 'category' => 'LPT', $column => $value])
        ->assertHasErrors([$column]);

    expect(Asset::count())->toBe(0);
})->with([
    'date that does not exist' => ['tgl_beli', '31/02/2026'],
    'price in words' => ['harga_beli', 'dua belas juta'],
    'BYOD answer other than ya or tidak' => ['is_byod', 'mungkin'],
]);

test('an admin limited to one company cannot import into another company', function () {
    Setting::put('company_separation', true);
    Company::factory()->create(['kode' => 'ABC']);

    AssetImporter::test()->import(['company' => 'ABC', 'nama' => 'Laptop', 'category' => 'LPT'])
        ->assertHasErrors(['company']);

    expect(Asset::withoutGlobalScopes()->count())->toBe(0);
});
