<?php

use App\Filament\Imports\LicenseImporter;
use App\Models\Company;
use App\Models\License;
use App\Models\Role;
use App\Models\Supplier;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->tcm = Company::factory()->create(['kode' => 'TCM']);
});

test('a license is created with Indonesian date and price formats under the role of the importing admin', function () {
    $this->actingAs(userWithRole('admin_it', attributes: ['company_id' => $this->tcm->id]));
    $supplier = Supplier::factory()->create(['nama' => 'PT Sumber Software']);

    $license = LicenseImporter::test()->import([
        'nama_software' => 'Microsoft 365 Business',
        'jumlah_seat' => '25',
        'tgl_habis' => '31/12/2026',
        'harga' => 'Rp 37.500.000',
        'supplier' => 'PT Sumber Software',
    ])->assertImported()->getRecord();

    expect($license->company_id)->toBe($this->tcm->id)
        ->and($license->role_id)->toBe(Role::findByName('admin_it')->id)
        ->and($license->jumlah_seat)->toBe(25)
        ->and($license->tgl_habis->toDateString())->toBe('2026-12-31')
        ->and($license->harga)->toBe(37500000)
        ->and($license->supplier_id)->toBe($supplier->id);
});

test('a product key in the file is never imported', function () {
    $this->actingAs(userWithRole('admin_it', attributes: ['company_id' => $this->tcm->id]));

    $license = LicenseImporter::test()->import(['nama_software' => 'Adobe Acrobat', 'jumlah_seat' => '5', 'product_key' => 'XXXXX-YYYYY-ZZZZZ'])
        ->assertImported()
        ->getRecord();

    expect($license->product_key)->toBeNull();
});

test('an admin cannot import a license for another managing role', function () {
    $this->actingAs(userWithRole('admin_it', attributes: ['company_id' => $this->tcm->id]));

    LicenseImporter::test()->import(['nama_software' => 'AutoCAD', 'jumlah_seat' => '2', 'role' => 'admin_ga'])
        ->assertHasErrors(['role']);

    expect(License::withoutGlobalScopes()->count())->toBe(0);
});

test('super admin must name the managing role', function () {
    $this->actingAs(userWithRole('super_admin', attributes: ['company_id' => $this->tcm->id]));

    LicenseImporter::test()->import(['nama_software' => 'AutoCAD', 'jumlah_seat' => '2'])
        ->assertHasRowFailure('Isi kolom pengelola.');

    $license = LicenseImporter::test()->import(['nama_software' => 'AutoCAD', 'jumlah_seat' => '2', 'role' => 'Admin GA'])
        ->assertImported()
        ->getRecord();

    expect($license->role_id)->toBe(Role::findByName('admin_ga')->id);
});

test('the number of seats must be at least one', function () {
    $this->actingAs(userWithRole('admin_it', attributes: ['company_id' => $this->tcm->id]));

    LicenseImporter::test()->import(['nama_software' => 'AutoCAD', 'jumlah_seat' => '0'])
        ->assertHasErrors(['jumlah_seat']);
});
