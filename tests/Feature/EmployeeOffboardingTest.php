<?php

use App\Filament\Resources\Employees\Pages\ManageEmployees;
use App\Models\Asset;
use App\Models\Assignment;
use App\Models\Category;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Role;
use App\Models\Status;
use Database\Seeders\RoleSeeder;
use Database\Seeders\StatusSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Collection;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed([RoleSeeder::class, StatusSeeder::class]);
    Filament::setCurrentPanel('admin');

    $this->tcm = Company::factory()->create();
    $this->account = userWithRole('karyawan', 'karyawan', ['company_id' => $this->tcm->id]);
    $this->employee = Employee::factory()->create(['company_id' => $this->tcm->id, 'user_id' => $this->account->id]);
    $this->adminIt = userWithRole('admin_it', attributes: ['company_id' => $this->tcm->id]);
});

function handLaptopTo(Employee $employee): Asset
{
    $laptop = Asset::factory()->create([
        'company_id' => $employee->company_id,
        'category_id' => Category::factory()->create(['role_id' => Role::findByName('admin_it')->id])->id,
        'status_id' => Status::named(Status::TERSEDIA)->id,
    ]);
    Assignment::handOver(new Collection([$laptop]), $employee, ['tanggal' => now()->toDateString(), 'kondisi' => 'Baik']);

    return $laptop->refresh();
}

test('an employee holding nothing is deactivated together with their login account', function () {
    $this->actingAs($this->adminIt);

    livewire(ManageEmployees::class)
        ->callAction(TestAction::make('deactivate')->table($this->employee))
        ->assertNotified('Karyawan dinonaktifkan');

    expect($this->employee->refresh()->is_active)->toBeFalse()
        ->and($this->account->refresh()->is_active)->toBeFalse();
});

test('an admin cannot deactivate an employee who still holds an asset, even by calling the action directly', function () {
    $this->actingAs($this->adminIt);
    $laptop = handLaptopTo($this->employee);

    livewire(ManageEmployees::class)
        ->callAction(TestAction::make('deactivate')->table($this->employee))
        ->assertNotified('Karyawan belum bisa dinonaktifkan');

    expect($this->employee->refresh()->is_active)->toBeTrue()
        ->and($laptop->refresh()->status->nama)->toBe(Status::DIPAKAI);
});

test('a super admin forcing the deactivation marks held assets lost and logs the reason', function () {
    $this->actingAs($this->adminIt);
    $laptop = handLaptopTo($this->employee);
    $this->actingAs(userWithRole('super_admin', attributes: ['company_id' => $this->tcm->id]));

    livewire(ManageEmployees::class)
        ->callAction(TestAction::make('deactivate')->table($this->employee), data: ['alasan' => 'Laptop hilang saat dinas'])
        ->assertHasNoFormErrors()
        ->assertNotified('Karyawan dinonaktifkan');

    $log = $this->employee->activitiesAsSubject()->where('event', 'dinonaktifkan_paksa')->sole();

    expect($this->employee->refresh()->is_active)->toBeFalse()
        ->and($this->account->refresh()->is_active)->toBeFalse()
        ->and($laptop->refresh()->status->nama)->toBe(Status::HILANG)
        ->and($log->properties['alasan'])->toBe('Laptop hilang saat dinas')
        ->and($log->properties['aset'])->toBe([$laptop->kode]);
});

test('a forced deactivation needs a reason', function () {
    $this->actingAs($this->adminIt);
    handLaptopTo($this->employee);
    $this->actingAs(userWithRole('super_admin', attributes: ['company_id' => $this->tcm->id]));

    livewire(ManageEmployees::class)
        ->callAction(TestAction::make('deactivate')->table($this->employee), data: ['alasan' => ''])
        ->assertHasFormErrors(['alasan' => 'required']);

    expect($this->employee->refresh()->is_active)->toBeTrue();
});

test('an employee who has received assets cannot be deleted', function () {
    $this->actingAs($this->adminIt);
    handLaptopTo($this->employee);

    livewire(ManageEmployees::class)
        ->callAction(TestAction::make('delete')->table($this->employee))
        ->assertNotified('Data tidak bisa dihapus');

    expect(Employee::find($this->employee->id))->not->toBeNull();
});
