<?php

use App\Filament\Imports\AssetImporter;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Imports\Models\Import;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->owner = userWithRole('admin_it');

    $this->import = Import::create([
        'file_name' => 'aset.csv',
        'file_path' => 'livewire-tmp/aset.csv',
        'importer' => AssetImporter::class,
        'total_rows' => 1,
        'user_id' => $this->owner->id,
    ]);
    $this->import->failedRows()->create([
        'data' => ['nama' => '=HYPERLINK("http://contoh.test","klik")', 'kategori' => 'XYZ'],
        'validation_error' => 'Kolom kategori yang dipilih tidak valid.',
    ]);
});

test('the owner downloads the failed rows with formulas neutralised', function () {
    $this->actingAs($this->owner);

    $response = $this->get(route('filament.imports.failed-rows.download', $this->import));

    $response->assertOk();
    expect($response->streamedContent())
        ->toContain('Kolom kategori yang dipilih tidak valid.')
        ->toContain("'=HYPERLINK");
});

test('another admin cannot download the failed rows', function () {
    $this->actingAs(userWithRole('admin_it'));

    $this->get(route('filament.imports.failed-rows.download', $this->import))->assertForbidden();
});

test('the owner cannot download before passing 2FA in this session', function () {
    // be() skips the 2FA session that actingAs() adds in TestCase.
    $this->be($this->owner);

    $this->get(route('filament.imports.failed-rows.download', $this->import))->assertForbidden();
});

test('a deactivated owner cannot download the failed rows', function () {
    $this->owner->update(['is_active' => false]);
    $this->actingAs($this->owner);

    $this->get(route('filament.imports.failed-rows.download', $this->import))->assertForbidden();
});
