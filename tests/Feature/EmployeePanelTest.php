<?php

use App\Enums\AssignmentStatus;
use App\Enums\MaintenanceStatus;
use App\Filament\Karyawan\Pages\MyAssets;
use App\Filament\Karyawan\Pages\MyDamageReports;
use App\Filament\Karyawan\Pages\MyHistory;
use App\Models\Asset;
use App\Models\Assignment;
use App\Models\Category;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Maintenance;
use App\Models\Role;
use App\Models\Status;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\StatusSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Schemas\Components\Text;
use Illuminate\Database\Eloquent\Collection;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed([RoleSeeder::class, StatusSeeder::class]);
    $this->tcm = Company::factory()->create(['kode' => 'TCM']);
    $this->laptop = Category::factory()->create([
        'role_id' => Role::findByName('admin_it')->id,
        'eula' => 'Laptop hanya untuk pekerjaan kantor.',
    ]);
    $this->adminIt = userWithRole('admin_it', attributes: ['company_id' => $this->tcm->id]);

    [$this->rinaUser, $this->rina] = employeeWithAccount($this->tcm, 'Rina');
    [$this->budiUser, $this->budi] = employeeWithAccount($this->tcm, 'Budi');
});

/**
 * @return array{0: User, 1: Employee}
 */
function employeeWithAccount(Company $company, string $nama): array
{
    $user = userWithRole('karyawan', 'karyawan', ['company_id' => $company->id]);

    return [$user, Employee::factory()->create(['company_id' => $company->id, 'nama' => $nama, 'user_id' => $user->id])];
}

/**
 * Admin IT menyerahkan sejumlah laptop ke karyawan dalam satu BAST.
 *
 * @return Collection<int, Asset>
 */
function handOverLaptops(int $count, Employee $employee): Collection
{
    $laptops = Asset::factory()->count($count)->create([
        'company_id' => $employee->company_id,
        'category_id' => test()->laptop->id,
        'status_id' => Status::named(Status::TERSEDIA)->id,
    ]);

    test()->actingAs(test()->adminIt);
    Assignment::handOver($laptops, $employee, ['tanggal' => now()->toDateString(), 'kondisi' => 'Baik']);

    return $laptops->fresh();
}

function actAsEmployee(User $user): void
{
    test()->actingAs($user);
    Filament::setCurrentPanel('karyawan');
}

test('an employee sees only the assets, history and reports that are theirs', function () {
    $rinaLaptop = handOverLaptops(1, $this->rina)->first();
    $budiLaptop = handOverLaptops(1, $this->budi)->first();
    Maintenance::factory()->create(['asset_id' => $budiLaptop->id, 'dilapor_oleh' => $this->budi->id]);
    actAsEmployee($this->rinaUser);

    livewire(MyAssets::class)
        ->assertCanSeeTableRecords([$rinaLaptop])
        ->assertCanNotSeeTableRecords([$budiLaptop]);

    livewire(MyHistory::class)
        ->assertCanSeeTableRecords(Assignment::withoutGlobalScopes()->where('asset_id', $rinaLaptop->id)->get())
        ->assertCanNotSeeTableRecords(Assignment::withoutGlobalScopes()->where('asset_id', $budiLaptop->id)->get());

    livewire(MyDamageReports::class)->assertCountTableRecords(0);
});

test('accepting one asset accepts the whole BAST once the terms are agreed to', function () {
    [$first, $second] = handOverLaptops(2, $this->rina)->all();
    actAsEmployee($this->rinaUser);

    livewire(MyAssets::class)
        ->mountAction(TestAction::make('accept')->table($first))
        ->assertSchemaComponentExists(
            "eula-{$this->laptop->id}",
            checkComponentUsing: fn (Text $component): bool => str_contains((string) $component->getContent(), 'Laptop hanya untuk pekerjaan kantor.'),
        )
        ->callMountedAction()
        ->assertHasFormErrors(['setuju' => 'accepted']);

    expect(Assignment::withoutGlobalScopes()->where('status', AssignmentStatus::Menunggu)->count())->toBe(2);

    livewire(MyAssets::class)
        ->callAction(TestAction::make('accept')->table($first), data: ['setuju' => true])
        ->assertNotified('Serah-terima diterima');

    $rows = Assignment::withoutGlobalScopes()->whereIn('asset_id', [$first->id, $second->id])->get();

    expect($rows->every(fn (Assignment $row): bool => $row->status === AssignmentStatus::Diterima && $row->accepted_at !== null))->toBeTrue();
});

test('an employee cannot accept a BAST addressed to someone else', function () {
    handOverLaptops(1, $this->rina);
    $noBast = Assignment::withoutGlobalScopes()->value('no_bast');

    expect(Assignment::accept($this->budi, $noBast))->toBe(0)
        ->and(Assignment::withoutGlobalScopes()->value('status'))->toBe(AssignmentStatus::Menunggu);
});

test('the menu counts BASTs still waiting to be accepted', function () {
    handOverLaptops(2, $this->rina);
    actAsEmployee($this->rinaUser);

    expect(MyAssets::getNavigationBadge())->toBe('1');
});

test('an employee downloads their own BAST but gets 404 for another employee\'s', function () {
    handOverLaptops(1, $this->rina);
    handOverLaptops(1, $this->budi);
    $rinaRow = Assignment::withoutGlobalScopes()->where('target_id', $this->rina->id)->sole();
    $budiRow = Assignment::withoutGlobalScopes()->where('target_id', $this->budi->id)->sole();
    actAsEmployee($this->rinaUser);

    $this->get(route('filament.karyawan.bast.download', $rinaRow))->assertOk()->assertHeader('content-type', 'application/pdf');
    $this->get(route('filament.karyawan.bast.download', $budiRow))->assertNotFound();
});

test('an employee reports damage on an asset they hold, once while the report is open', function () {
    $laptop = handOverLaptops(1, $this->rina)->first();
    actAsEmployee($this->rinaUser);

    livewire(MyAssets::class)
        ->callAction(TestAction::make('report')->table($laptop), data: ['keluhan' => 'Layar berkedip'])
        ->assertNotified('Laporan terkirim');

    livewire(MyAssets::class)
        ->callAction(TestAction::make('report')->table($laptop), data: ['keluhan' => 'Masih berkedip'])
        ->assertNotified('Laporan belum terkirim');

    $report = Maintenance::withoutGlobalScopes()->sole();

    expect($report->status)->toBe(MaintenanceStatus::Dilaporkan)
        ->and($report->dilapor_oleh)->toBe($this->rina->id)
        ->and($report->dibuat_oleh)->toBe($this->rinaUser->id)
        ->and($report->keluhan)->toBe('Layar berkedip');
});

test('an employee cannot report an asset someone else holds', function () {
    $budiLaptop = handOverLaptops(1, $this->budi)->first();
    actAsEmployee($this->rinaUser);

    livewire(MyDamageReports::class)
        ->callAction('report', data: ['asset_id' => $budiLaptop->id, 'keluhan' => 'Rusak'])
        ->assertHasFormErrors(['asset_id']);

    expect(fn () => Maintenance::report($this->rina, $budiLaptop, 'Rusak'))->toThrow(LogicException::class)
        ->and(Maintenance::withoutGlobalScopes()->count())->toBe(0);
});

test('the guide opens without logging in and is linked from the login page', function () {
    $this->withoutVite();

    $this->get('/panduan')->assertOk()->assertSee('Panduan karyawan')->assertSee('Menerima aset dari admin');
    $this->get('/login')->assertSee(route('panduan'));
});
