<?php

use App\Enums\StockMovement;
use App\Filament\Resources\Consumables\ConsumableResource;
use App\Filament\Resources\Consumables\Pages\CreateConsumable;
use App\Filament\Resources\Consumables\Pages\EditConsumable;
use App\Filament\Resources\Consumables\Pages\ListConsumables;
use App\Filament\Resources\Consumables\Pages\ViewConsumable;
use App\Filament\Resources\Consumables\RelationManagers\TransactionsRelationManager;
use App\Filament\Widgets\LowStockConsumables;
use App\Models\Company;
use App\Models\Consumable;
use App\Models\Employee;
use App\Models\Role;
use App\Models\Supplier;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Filament::setCurrentPanel('admin');

    $this->tcm = Company::factory()->create();
    $this->adminIt = userWithRole('admin_it', attributes: ['company_id' => $this->tcm->id]);
    $this->toner = Consumable::factory()->create(['company_id' => $this->tcm->id, 'nama' => 'Toner HP 85A', 'satuan' => 'pcs', 'stok_minimum' => 3]);
    $this->budi = Employee::factory()->create(['company_id' => $this->tcm->id]);

    $this->actingAs($this->adminIt);
});

test('stock in adds to the stock, stock out takes from it, and each movement keeps the stock after it', function () {
    $supplier = Supplier::factory()->create();

    $in = $this->toner->receive(10, ['supplier_id' => $supplier->id, 'tanggal' => '2026-11-10']);
    $out = $this->toner->issue(4, $this->budi, ['catatan' => 'Printer lantai 2']);

    expect($this->toner->stok)->toBe(6)
        ->and($in->jenis)->toBe(StockMovement::Masuk)
        ->and($in->stok_sesudah)->toBe(10)
        ->and($in->tanggal->toDateString())->toBe('2026-11-10')
        ->and($out->stok_sesudah)->toBe(6)
        ->and($out->employee_id)->toBe($this->budi->id)
        ->and($out->dibuat_oleh)->toBe($this->adminIt->id);
});

test('stock out is refused beyond the stock, for zero, and for inactive or outside employees', function () {
    $this->toner->receive(2);
    $inactive = Employee::factory()->create(['company_id' => $this->tcm->id, 'is_active' => false]);
    $outsider = Employee::factory()->create();

    expect(fn () => $this->toner->issue(3, $this->budi))->toThrow(LogicException::class, 'tinggal 2 pcs')
        ->and(fn () => $this->toner->issue(0, $this->budi))->toThrow(LogicException::class, 'minimal 1')
        ->and(fn () => $this->toner->issue(1, $inactive))->toThrow(LogicException::class, 'nonaktif')
        ->and(fn () => $this->toner->issue(1, $outsider))->toThrow(LogicException::class, 'perusahaan lain')
        ->and($this->toner->refresh()->stok)->toBe(2);
});

test('the stock cannot be edited directly and the history cannot be changed', function () {
    $movement = $this->toner->receive(5);

    $this->toner->update(['stok' => 999]);

    expect($this->toner->refresh()->stok)->toBe(5)
        ->and(fn () => $this->toner->forceFill(['stok' => 999])->save())->toThrow(LogicException::class, 'lewat transaksi')
        ->and(fn () => $movement->update(['jumlah' => 1]))->toThrow(LogicException::class, 'tidak bisa diubah')
        ->and(fn () => $movement->delete())->toThrow(LogicException::class, 'tidak bisa dihapus');
});

test('an item with movements cannot be deleted, a new one can', function () {
    $this->toner->receive(1);
    $unused = Consumable::factory()->create(['company_id' => $this->tcm->id]);

    livewire(EditConsumable::class, ['record' => $this->toner->id])->assertActionHidden('delete');
    livewire(EditConsumable::class, ['record' => $unused->id])->callAction('delete');

    expect(fn () => $this->toner->delete())->toThrow(LogicException::class, 'tidak bisa dihapus')
        ->and(Consumable::query()->find($unused->id))->toBeNull();
});

test('an admin records a new item with its starting stock', function () {
    livewire(CreateConsumable::class)
        ->fillForm([
            'company_id' => $this->tcm->id,
            'nama' => 'Kertas A4 80gr',
            'satuan' => 'rim',
            'role_id' => Role::findByName('admin_it')->id,
            'stok_minimum' => 10,
            'stok_awal' => 25,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $paper = Consumable::query()->where('nama', 'Kertas A4 80gr')->sole();

    expect($paper->stok)->toBe(25)
        ->and($paper->transactions()->sole()->catatan)->toBe('Stok awal');
});

test('stock in and out from the list page, refused when the stock runs out', function () {
    livewire(ListConsumables::class)
        ->callAction(TestAction::make('receive')->table($this->toner), data: ['jumlah' => 5, 'tanggal' => today()->toDateString()])
        ->assertNotified('Stok masuk tercatat')
        ->callAction(TestAction::make('issue')->table($this->toner), data: ['jumlah' => 5, 'employee_id' => $this->budi->id, 'tanggal' => today()->toDateString()])
        ->assertNotified('Stok keluar tercatat');

    expect($this->toner->refresh()->stok)->toBe(0);

    livewire(ViewConsumable::class, ['record' => $this->toner->id])
        ->assertActionDisabled('issue')
        ->callAction('receive', data: ['jumlah' => 2, 'tanggal' => today()->toDateString()])
        ->callAction('issue', data: ['jumlah' => 3, 'employee_id' => $this->budi->id, 'tanggal' => today()->toDateString()])
        ->assertHasActionErrors(['jumlah' => 'max']);

    livewire(TransactionsRelationManager::class, ['ownerRecord' => $this->toner, 'pageClass' => ViewConsumable::class])
        ->assertCanSeeTableRecords($this->toner->transactions()->get());
});

test('an admin of another role does not see the item, and an employee cannot open the menu', function () {
    $this->actingAs(userWithRole('admin_ga', attributes: ['company_id' => $this->tcm->id]));

    expect(Consumable::query()->find($this->toner->id))->toBeNull();
    $this->get(ConsumableResource::getUrl('view', ['record' => $this->toner]))->assertNotFound();

    $this->actingAs(userWithRole('karyawan', 'karyawan', ['company_id' => $this->tcm->id]));

    $this->get(ConsumableResource::getUrl('index'))->assertForbidden();
});

test('the dashboard lists items at or below their minimum, but not unmonitored ones', function () {
    $this->toner->receive(3);
    $paper = Consumable::factory()->create(['company_id' => $this->tcm->id, 'stok_minimum' => 10]);
    $paper->receive(50);
    $unmonitored = Consumable::factory()->create(['company_id' => $this->tcm->id, 'stok_minimum' => 0]);

    livewire(LowStockConsumables::class)
        ->assertCanSeeTableRecords([$this->toner])
        ->assertCanNotSeeTableRecords([$paper, $unmonitored]);
});
