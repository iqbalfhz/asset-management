<?php

use App\Enums\DisposalMethod;
use App\Filament\Exports\LicenseExporter;
use App\Filament\Resources\Assets\Pages\ViewAsset;
use App\Filament\Resources\Assets\RelationManagers\LicenseSeatsRelationManager;
use App\Filament\Resources\Employees\Pages\ManageEmployees;
use App\Filament\Resources\Licenses\LicenseResource;
use App\Filament\Resources\Licenses\Pages\CreateLicense;
use App\Filament\Resources\Licenses\Pages\EditLicense;
use App\Filament\Resources\Licenses\Pages\ViewLicense;
use App\Filament\Resources\Licenses\RelationManagers\SeatsRelationManager;
use App\Filament\Widgets\LicensesExpiring;
use App\Models\Asset;
use App\Models\Category;
use App\Models\Company;
use App\Models\Disposal;
use App\Models\Employee;
use App\Models\License;
use App\Models\LicenseSeat;
use App\Models\Role;
use App\Models\Status;
use Database\Seeders\RoleSeeder;
use Database\Seeders\StatusSeeder;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed([RoleSeeder::class, StatusSeeder::class]);

    $this->tcm = Company::factory()->create();
    $this->adminIt = userWithRole('admin_it', attributes: ['company_id' => $this->tcm->id]);
    $this->license = License::factory()->create(['company_id' => $this->tcm->id, 'nama_software' => 'Microsoft 365', 'jumlah_seat' => 2, 'product_key' => 'ABCDE-12345-FGHIJ']);
    [$this->budi, $this->sari, $this->tono] = Employee::factory()->count(3)->create(['company_id' => $this->tcm->id])->all();
    $this->laptop = Asset::factory()->create([
        'company_id' => $this->tcm->id,
        'category_id' => Category::factory()->create(['role_id' => Role::findByName('admin_it')->id])->id,
        'status_id' => Status::named(Status::TERSEDIA)->id,
    ]);

    $this->actingAs($this->adminIt);
});

test('seats can be assigned up to the license count, and a released seat can be used again', function () {
    $budiSeat = $this->license->assignSeat($this->budi);
    $this->license->assignSeat($this->laptop);

    expect($this->license->usedSeats())->toBe(2)
        ->and($budiSeat->dipasang_oleh)->toBe($this->adminIt->id)
        ->and(fn () => $this->license->assignSeat($this->sari))->toThrow(LogicException::class, 'sudah habis');

    $budiSeat->release();

    expect($budiSeat->dilepas_pada)->not->toBeNull()
        ->and($this->license->assignSeat($this->sari)->employee_id)->toBe($this->sari->id)
        ->and(fn () => $budiSeat->release())->toThrow(LogicException::class, 'sudah dilepas');
});

test('the same employee or asset cannot take two seats of one license', function () {
    $this->license->assignSeat($this->budi);

    expect(fn () => $this->license->assignSeat($this->budi))->toThrow(LogicException::class, 'sudah memakai seat');
});

test('seats are refused for inactive employees, disposed assets, expired licenses, and another company', function () {
    $this->tono->update(['is_active' => false]);
    $this->laptop->transitionTo(Status::named(Status::DIHAPUSKAN));
    $expired = License::factory()->create(['company_id' => $this->tcm->id, 'tgl_habis' => now()->subDay()]);
    $outsider = Employee::factory()->create();

    expect(fn () => $this->license->assignSeat($this->tono))->toThrow(LogicException::class, 'sudah nonaktif')
        ->and(fn () => $this->license->assignSeat($this->laptop->refresh()))->toThrow(LogicException::class, 'sudah dihapuskan')
        ->and(fn () => $expired->assignSeat($this->budi))->toThrow(LogicException::class, 'lewat tanggal habis')
        ->and(fn () => $this->license->assignSeat($outsider))->toThrow(LogicException::class, 'perusahaan lain');
});

test('the seat count cannot go below the seats in use', function () {
    $this->license->assignSeat($this->budi);
    $this->license->assignSeat($this->sari);

    expect(fn () => $this->license->update(['jumlah_seat' => 1]))->toThrow(LogicException::class, 'di bawah 2 seat');

    $this->license->update(['jumlah_seat' => 3]);

    expect($this->license->refresh()->jumlah_seat)->toBe(3);
});

test('the product key is stored encrypted and kept out of the activity log', function () {
    $this->license->update(['product_key' => 'NEW01-KEY02-XYZ03']);

    $stored = DB::table('licenses')->where('id', $this->license->id)->value('product_key');
    $logged = Activity::query()->where('subject_type', $this->license->getMorphClass())->where('subject_id', $this->license->id)->get();

    expect($stored)->not->toContain('NEW01')
        ->and($this->license->refresh()->product_key)->toBe('NEW01-KEY02-XYZ03')
        ->and($logged)->not->toBeEmpty()
        ->and($logged->pluck('properties')->toJson())->not->toContain('ABCDE')->not->toContain('NEW01');
});

test('an admin of another role does not see the license or its seats', function () {
    $this->license->assignSeat($this->budi);

    $this->actingAs(userWithRole('admin_ga', attributes: ['company_id' => $this->tcm->id]));

    expect(License::query()->find($this->license->id))->toBeNull()
        ->and(LicenseSeat::query()->count())->toBe(0);

    $this->get(LicenseResource::getUrl('view', ['record' => $this->license]))->assertNotFound();
});

test('an employee cannot open the license menu', function () {
    $this->actingAs(userWithRole('karyawan', 'karyawan', ['company_id' => $this->tcm->id]));

    $this->get(LicenseResource::getUrl('index'))->assertForbidden();
});

test('an admin records a license and only picks their own role as manager', function () {
    Filament::setCurrentPanel('admin');

    livewire(CreateLicense::class)
        ->assertFormFieldExists('role_id', fn (Select $field): bool => array_keys($field->getOptions()) === [Role::findByName('admin_it')->id])
        ->fillForm([
            'company_id' => $this->tcm->id,
            'nama_software' => 'AutoCAD LT',
            'role_id' => Role::findByName('admin_it')->id,
            'product_key' => 'ACAD1-ACAD2',
            'jumlah_seat' => 3,
            'tgl_habis' => now()->addYear()->toDateString(),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(License::query()->where('nama_software', 'AutoCAD LT')->sole()->product_key)->toBe('ACAD1-ACAD2');
});

test('the edit form refuses fewer seats than are in use', function () {
    Filament::setCurrentPanel('admin');
    $this->license->assignSeat($this->budi);
    $this->license->assignSeat($this->sari);

    livewire(EditLicense::class, ['record' => $this->license->id])
        ->fillForm(['jumlah_seat' => 1])
        ->call('save')
        ->assertHasFormErrors(['jumlah_seat' => 'min']);

    expect($this->license->refresh()->jumlah_seat)->toBe(2);
});

test('seats are assigned and released from the license page', function () {
    Filament::setCurrentPanel('admin');

    livewire(SeatsRelationManager::class, ['ownerRecord' => $this->license, 'pageClass' => ViewLicense::class])
        ->callAction(TestAction::make('assign')->table(), data: ['jenis' => 'karyawan', 'employee_id' => $this->budi->id])
        ->assertNotified('Seat dipasang')
        ->callAction(TestAction::make('assign')->table(), data: ['jenis' => 'aset', 'asset_id' => $this->laptop->id])
        ->assertNotified('Seat dipasang')
        ->callAction(TestAction::make('assign')->table(), data: ['jenis' => 'karyawan', 'employee_id' => $this->sari->id])
        ->assertNotified('Aksi dibatalkan');

    $seat = $this->license->seats()->where('employee_id', $this->budi->id)->sole();

    livewire(SeatsRelationManager::class, ['ownerRecord' => $this->license, 'pageClass' => ViewLicense::class])
        ->callAction(TestAction::make('release')->table($seat))
        ->assertNotified('Seat dilepas');

    expect($this->license->usedSeats())->toBe(1);
});

test('the asset page lists the licenses installed on it', function () {
    Filament::setCurrentPanel('admin');
    $seat = $this->license->assignSeat($this->laptop);

    livewire(LicenseSeatsRelationManager::class, ['ownerRecord' => $this->laptop, 'pageClass' => ViewAsset::class])
        ->assertCanSeeTableRecords([$seat])
        ->callAction(TestAction::make('release')->table($seat))
        ->assertNotified('Seat dilepas');
});

test('an employee holding a seat is not deactivated by an admin, and a forced deactivation releases the seat', function () {
    Filament::setCurrentPanel('admin');
    $seat = $this->license->assignSeat($this->budi);

    livewire(ManageEmployees::class)
        ->callAction(TestAction::make('deactivate')->table($this->budi))
        ->assertNotified('Karyawan belum bisa dinonaktifkan');

    expect($this->budi->refresh()->is_active)->toBeTrue();

    $this->actingAs(userWithRole('super_admin', attributes: ['company_id' => $this->tcm->id]));

    livewire(ManageEmployees::class)
        ->callAction(TestAction::make('deactivate')->table($this->budi), data: ['alasan' => 'Resign mendadak'])
        ->assertNotified('Karyawan dinonaktifkan');

    expect($this->budi->refresh()->is_active)->toBeFalse()
        ->and($seat->refresh()->dilepas_pada)->not->toBeNull();
});

test('approving the disposal of an asset releases its seats', function () {
    $seat = $this->license->assignSeat($this->laptop);
    $this->laptop->transitionTo(Status::named(Status::RUSAK));
    $disposal = Disposal::submit($this->laptop, ['metode' => DisposalMethod::Dibuang, 'alasan' => 'Mati total']);

    $this->actingAs(userWithRole('super_admin', attributes: ['company_id' => $this->tcm->id]));
    $disposal->approve();

    expect($seat->refresh()->dilepas_pada)->not->toBeNull();
});

test('the dashboard lists licenses that expire soon', function () {
    Filament::setCurrentPanel('admin');
    $soon = License::factory()->create(['company_id' => $this->tcm->id, 'tgl_habis' => now()->addDays(10)]);
    $expired = License::factory()->create(['company_id' => $this->tcm->id, 'tgl_habis' => now()->subDays(3)]);
    $perpetual = License::factory()->create(['company_id' => $this->tcm->id, 'tgl_habis' => null]);

    livewire(LicensesExpiring::class)
        ->assertCanSeeTableRecords([$soon, $expired])
        ->assertCanNotSeeTableRecords([$this->license, $perpetual]);
});

test('the license export carries seat usage but never the product key', function () {
    $this->license->assignSeat($this->budi);

    $row = LicenseExporter::test(columnMap: ['nama_software' => 'Software', 'jumlah_seat' => 'Jumlah seat', 'active_seats_count' => 'Seat terpakai'])
        ->export(License::query()->withCount('activeSeats')->find($this->license->id));

    expect($row)->toBe(['Microsoft 365', '2', '1'])
        ->and(collect(LicenseExporter::getColumns())->map(fn (ExportColumn $column): string => $column->getName()))->not->toContain('product_key');
});
