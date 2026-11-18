<?php

use App\Filament\Resources\Accessories\AccessoryResource;
use App\Filament\Resources\Accessories\Pages\ListAccessories;
use App\Filament\Resources\Accessories\Pages\ViewAccessory;
use App\Filament\Resources\Accessories\RelationManagers\CheckoutsRelationManager;
use App\Filament\Resources\Employees\Pages\ViewEmployee;
use App\Filament\Resources\Employees\RelationManagers\AccessoryCheckoutsRelationManager;
use App\Models\Accessory;
use App\Models\AccessoryCheckout;
use App\Models\Company;
use App\Models\Employee;
use Database\Seeders\RoleSeeder;
use Database\Seeders\StatusSeeder;
use Filament\Actions\DeleteAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Spatie\Activitylog\Models\Activity;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed([RoleSeeder::class, StatusSeeder::class]);
    Filament::setCurrentPanel('admin');

    $this->tcm = Company::factory()->create();
    $this->adminIt = userWithRole('admin_it', attributes: ['company_id' => $this->tcm->id]);
    $this->mouse = Accessory::factory()->create(['company_id' => $this->tcm->id, 'nama' => 'Mouse Logitech', 'jumlah' => 3]);
    $this->budi = Employee::factory()->create(['company_id' => $this->tcm->id, 'nama' => 'Budi']);

    $this->actingAs($this->adminIt);
});

test('checking out and returning moves what is available', function () {
    $checkout = $this->mouse->checkOut($this->budi, 2, 'Untuk presentasi');

    expect($this->mouse->availableQuantity())->toBe(1)
        ->and($checkout->diserahkan_oleh)->toBe($this->adminIt->id)
        ->and($checkout->catatan)->toBe('Untuk presentasi');

    $checkout->release();

    expect($this->mouse->availableQuantity())->toBe(3)
        ->and($checkout->dikembalikan_oleh)->toBe($this->adminIt->id)
        ->and(fn () => $checkout->release())->toThrow(LogicException::class, 'sudah kembali');
});

test('a check-out above what is available, of zero, or to an inactive or other-company employee is refused', function () {
    $this->mouse->checkOut($this->budi, 2);
    $resigned = Employee::factory()->create(['company_id' => $this->tcm->id, 'is_active' => false]);
    $outsider = Employee::factory()->create();

    expect(fn () => $this->mouse->checkOut($this->budi, 2))->toThrow(LogicException::class, 'tinggal 1 yang tersedia')
        ->and(fn () => $this->mouse->checkOut($this->budi, 0))->toThrow(LogicException::class, 'minimal 1')
        ->and(fn () => $this->mouse->checkOut($resigned, 1))->toThrow(LogicException::class, 'nonaktif')
        ->and(fn () => $this->mouse->checkOut($outsider, 1))->toThrow(LogicException::class, 'perusahaan lain')
        ->and($this->mouse->availableQuantity())->toBe(1);
});

test('the quantity cannot drop below what is held, and a used accessory is not deleted', function () {
    $this->mouse->checkOut($this->budi, 2);

    expect(fn () => $this->mouse->update(['jumlah' => 1]))->toThrow(LogicException::class, 'di bawah 2');

    $this->mouse->update(['jumlah' => 5]);

    livewire(ViewAccessory::class, ['record' => $this->mouse->getRouteKey()])
        ->callAction(DeleteAction::class)
        ->assertNotified('Data tidak bisa dihapus');

    expect($this->mouse->refresh()->availableQuantity())->toBe(3);
});

test('an admin checks out and takes back from the accessory page', function () {
    livewire(CheckoutsRelationManager::class, ['ownerRecord' => $this->mouse, 'pageClass' => ViewAccessory::class])
        ->callAction(TestAction::make('checkOut')->table(), data: ['employee_id' => $this->budi->id, 'jumlah' => 2])
        ->assertNotified('Aksesori diserahkan');

    $checkout = AccessoryCheckout::query()->sole();

    expect($checkout->employee_id)->toBe($this->budi->id)
        ->and($checkout->jumlah)->toBe(2);

    livewire(AccessoryCheckoutsRelationManager::class, ['ownerRecord' => $this->budi, 'pageClass' => ViewEmployee::class])
        ->assertCanSeeTableRecords([$checkout])
        ->callAction(TestAction::make('release')->table($checkout))
        ->assertNotified('Aksesori dikembalikan');

    expect($checkout->refresh()->isOpen())->toBeFalse();
});

test('an admin of another role does not see the accessory, and karyawan cannot open the menu', function () {
    $this->actingAs(userWithRole('admin_ga', attributes: ['company_id' => $this->tcm->id]));

    livewire(ListAccessories::class)->assertCanNotSeeTableRecords([$this->mouse]);

    $this->actingAs(userWithRole('karyawan', 'karyawan'))
        ->get(AccessoryResource::getUrl())
        ->assertForbidden();
});

test('an employee holding an accessory is only deactivated when forced, which writes the units off', function () {
    $this->mouse->checkOut($this->budi, 2);

    expect(fn () => $this->budi->deactivate())->toThrow(LogicException::class, '2 aksesori')
        ->and($this->budi->refresh()->is_active)->toBeTrue();

    $this->actingAs(userWithRole('super_admin', attributes: ['company_id' => $this->tcm->id]));
    $this->budi->deactivate('Pindah kerja, mouse tidak kembali');

    expect($this->budi->refresh()->is_active)->toBeFalse()
        ->and($this->mouse->refresh()->jumlah)->toBe(1)
        ->and($this->mouse->availableQuantity())->toBe(1)
        ->and(Activity::query()->where('event', 'dinonaktifkan_paksa')->sole()->properties['aksesori'])->toBe(['2 × Mouse Logitech']);
});

test('the accessory list and page show what is available', function () {
    $this->mouse->checkOut($this->budi, 1);

    $this->get(AccessoryResource::getUrl())->assertOk()->assertSee('2 dari 3');
    $this->get(AccessoryResource::getUrl('view', ['record' => $this->mouse]))->assertOk()->assertSee('2 dari 3');
});
