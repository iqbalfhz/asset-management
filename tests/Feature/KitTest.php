<?php

use App\Filament\Resources\Kits\KitResource;
use App\Filament\Resources\Kits\Pages\CreateKit;
use App\Filament\Resources\Kits\Pages\ListKits;
use App\Models\Accessory;
use App\Models\AssetModel;
use App\Models\Assignment;
use App\Models\Category;
use App\Models\Company;
use App\Models\Consumable;
use App\Models\ConsumableTransaction;
use App\Models\Employee;
use App\Models\Kit;
use App\Models\License;
use App\Models\Manufacturer;
use App\Models\Role;
use App\Models\Status;
use Database\Seeders\RoleSeeder;
use Database\Seeders\StatusSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed([RoleSeeder::class, StatusSeeder::class]);
    Filament::setCurrentPanel('admin');

    $this->tcm = Company::factory()->create();
    $this->adminIt = userWithRole('admin_it', attributes: ['company_id' => $this->tcm->id]);
    $laptops = Category::factory()->create(['role_id' => Role::findByName('admin_it')->id]);
    $this->thinkpad = AssetModel::factory()->create([
        'category_id' => $laptops->id,
        'manufacturer_id' => Manufacturer::factory()->create(['nama' => 'Lenovo'])->id,
        'nama' => 'ThinkPad E14',
    ]);
    $this->firstLaptop = availableAsset($this->tcm, $laptops, ['asset_model_id' => $this->thinkpad->id]);
    $this->secondLaptop = availableAsset($this->tcm, $laptops, ['asset_model_id' => $this->thinkpad->id]);
    $this->mouse = Accessory::factory()->create(['company_id' => $this->tcm->id, 'nama' => 'Mouse Logitech', 'jumlah' => 2]);
    $this->office = License::factory()->create(['company_id' => $this->tcm->id, 'nama_software' => 'Microsoft 365', 'jumlah_seat' => 1]);
    $this->paper = Consumable::factory()->create(['company_id' => $this->tcm->id, 'nama' => 'Kertas A4']);
    $this->budi = Employee::factory()->create(['company_id' => $this->tcm->id, 'nama' => 'Budi']);

    $this->actingAs($this->adminIt);
    $this->paper->receive(5);

    $this->kit = Kit::factory()->create(['company_id' => $this->tcm->id, 'nama' => 'Laptop staf']);
    $this->laptopLine = $this->kit->items()->create(['item_type' => AssetModel::class, 'item_id' => $this->thinkpad->id, 'jumlah' => 1]);
    $this->kit->items()->create(['item_type' => Accessory::class, 'item_id' => $this->mouse->id, 'jumlah' => 1]);
    $this->kit->items()->create(['item_type' => License::class, 'item_id' => $this->office->id, 'jumlah' => 1]);
    $this->kit->items()->create(['item_type' => Consumable::class, 'item_id' => $this->paper->id, 'jumlah' => 2]);
});

function handOverKit(?array $laptopIds = null): ?string
{
    return test()->kit->handOver(
        test()->budi,
        [test()->laptopLine->id => $laptopIds ?? [test()->secondLaptop->id]],
        ['tanggal' => today()->toDateString(), 'kondisi' => 'Baik'],
    );
}

test('handing over a kit records each item on its own, with one BAST for the chosen asset', function () {
    livewire(ListKits::class)
        ->callAction(TestAction::make('handOver')->table($this->kit), data: [
            'employee_id' => $this->budi->id,
            'assets' => [$this->laptopLine->id => [$this->secondLaptop->id]],
            'tanggal' => today()->toDateString(),
            'kondisi' => 'Baik',
        ])
        ->assertNotified('Paket diserahkan');

    expect($this->secondLaptop->refresh()->assigned->is($this->budi))->toBeTrue()
        ->and($this->firstLaptop->refresh()->status->nama)->toBe(Status::TERSEDIA)
        ->and(Assignment::query()->sole()->catatan)->toBe('Paket Laptop staf')
        ->and($this->mouse->availableQuantity())->toBe(1)
        ->and($this->office->usedSeats())->toBe(1)
        ->and($this->paper->refresh()->stok)->toBe(3)
        ->and(ConsumableTransaction::query()->latest('id')->first()->catatan)->toBe('Paket Laptop staf');
});

test('when one item cannot be given, nothing from the kit is handed over', function () {
    $this->office->assignSeat(Employee::factory()->create(['company_id' => $this->tcm->id]));

    expect(fn () => handOverKit())->toThrow(LogicException::class, 'sudah habis')
        ->and($this->secondLaptop->refresh()->status->nama)->toBe(Status::TERSEDIA)
        ->and($this->mouse->availableQuantity())->toBe(2)
        ->and($this->paper->refresh()->stok)->toBe(5)
        ->and(Assignment::query()->count())->toBe(0);
});

test('the chosen assets must be of the kit model and exactly as many as the kit asks', function () {
    $otherModel = availableAsset($this->tcm, $this->firstLaptop->category);

    expect(fn () => handOverKit([$otherModel->id]))->toThrow(LogicException::class, 'Pilih 1 aset Lenovo ThinkPad E14')
        ->and(fn () => handOverKit([$this->firstLaptop->id, $this->secondLaptop->id]))->toThrow(LogicException::class, 'Pilih 1 aset');
});

test('an admin cannot hand over kit items managed by another role', function () {
    $mouseOnly = Kit::factory()->create(['company_id' => $this->tcm->id]);
    $mouseOnly->items()->create(['item_type' => Accessory::class, 'item_id' => $this->mouse->id, 'jumlah' => 1]);
    $this->actingAs(userWithRole('admin_ga', attributes: ['company_id' => $this->tcm->id]));

    expect(fn () => $mouseOnly->handOver($this->budi, [], ['tanggal' => today()->toDateString(), 'kondisi' => 'Baik']))
        ->toThrow(LogicException::class, 'tidak bisa Anda serahkan')
        ->and($this->mouse->availableQuantity())->toBe(2);
});

test('a kit is built from items of its own company only', function () {
    $undoRepeaterFake = Repeater::fake();
    $otherCompanyMouse = Accessory::factory()->create(['nama' => 'Mouse PT lain']);

    livewire(CreateKit::class)
        ->fillForm(['company_id' => $this->tcm->id, 'nama' => 'Paket baru', 'items' => [['item_type' => Accessory::class, 'item_id' => $otherCompanyMouse->id, 'jumlah' => 1]]])
        ->call('create')
        ->assertHasFormErrors(['items.0.item_id']);

    livewire(CreateKit::class)
        ->fillForm(['company_id' => $this->tcm->id, 'nama' => 'Paket baru', 'items' => [['item_type' => Accessory::class, 'item_id' => $this->mouse->id, 'jumlah' => 2]]])
        ->call('create')
        ->assertHasNoFormErrors();

    $undoRepeaterFake();

    expect(Kit::query()->where('nama', 'Paket baru')->sole()->items->sole()->label())->toBe('2 × Mouse Logitech');
});

test('the kit list shows what is inside, and karyawan cannot open it', function () {
    $this->get(KitResource::getUrl())->assertOk()->assertSee('1 × Mouse Logitech')->assertSee('1 × Lenovo ThinkPad E14');

    $this->actingAs(userWithRole('karyawan', 'karyawan'))
        ->get(KitResource::getUrl())
        ->assertForbidden();
});
