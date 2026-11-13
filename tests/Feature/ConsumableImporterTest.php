<?php

use App\Enums\StockMovement;
use App\Filament\Imports\ConsumableImporter;
use App\Models\Company;
use App\Models\Consumable;
use App\Models\Role;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->tcm = Company::factory()->create(['kode' => 'TCM']);
    $this->actingAs(userWithRole('admin_ga', attributes: ['company_id' => $this->tcm->id]));
});

test('the initial stock of an imported item is recorded as an incoming transaction', function () {
    $item = ConsumableImporter::test()->import(['nama' => 'Kertas A4', 'satuan' => 'rim', 'stok_minimum' => '5', 'stok_awal' => '40'])
        ->assertImported()
        ->getRecord()
        ->refresh();

    $transaction = $item->transactions()->sole();

    expect($item->company_id)->toBe($this->tcm->id)
        ->and($item->role_id)->toBe(Role::findByName('admin_ga')->id)
        ->and($item->stok)->toBe(40)
        ->and($item->stok_minimum)->toBe(5)
        ->and($transaction->jenis)->toBe(StockMovement::Masuk)
        ->and($transaction->jumlah)->toBe(40)
        ->and($transaction->catatan)->toBe('Stok awal dari import');
});

test('an item without initial stock has no transaction', function () {
    $item = ConsumableImporter::test()->import(['nama' => 'Tinta Printer', 'satuan' => 'botol'])
        ->assertImported()
        ->getRecord()
        ->refresh();

    expect($item->stok)->toBe(0)
        ->and($item->transactions()->count())->toBe(0);
});

test('an item name that already exists in the company fails the row, even under another managing role', function () {
    Consumable::factory()->create(['company_id' => $this->tcm->id, 'nama' => 'Kertas A4', 'role_id' => Role::findByName('admin_it')->id]);

    ConsumableImporter::test()->import(['nama' => 'Kertas A4', 'satuan' => 'rim'])
        ->assertHasRowFailure('Barang Kertas A4 sudah ada.');

    expect(Consumable::withoutGlobalScopes()->count())->toBe(1);
});

test('a negative initial stock is rejected', function () {
    ConsumableImporter::test()->import(['nama' => 'Kertas A4', 'satuan' => 'rim', 'stok_awal' => '-3'])
        ->assertHasErrors(['stok_awal']);

    expect(Consumable::withoutGlobalScopes()->count())->toBe(0);
});
