<?php

use App\Filament\Resources\Companies\CompanyResource;
use App\Filament\Resources\Companies\Pages\ManageCompanies;
use App\Filament\Resources\Roles\RoleResource;
use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Filament\Resources\Users\UserResource;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Actions\CreateAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Filament::setCurrentPanel('admin');
    $this->company = Company::factory()->create();
    $this->superAdmin = userWithRole('super_admin', attributes: ['company_id' => $this->company->id]);
});

test('admin IT cannot open users, companies or roles even when given the permission', function (string $resource) {
    $adminIt = userWithRole('admin_it');
    Role::findByName('admin_it')->givePermissionTo(Permission::findOrCreate('ViewAny:User'), Permission::findOrCreate('ViewAny:Company'));

    $this->actingAs($adminIt)->get($resource::getUrl('index'))->assertForbidden();
})->with([UserResource::class, CompanyResource::class, RoleResource::class]);

test('super admin creates a user with exactly one role', function () {
    $this->actingAs($this->superAdmin);

    livewire(ManageUsers::class)
        ->callAction(CreateAction::class, data: [
            'name' => 'Budi Santoso',
            'username' => 'budi',
            'email' => 'budi@tangcity.com',
            'company_id' => $this->company->id,
            'role' => Role::findByName('admin_ga')->id,
            'is_active' => true,
            'password' => 'Kantor2026aman',
        ])
        ->assertHasNoActionErrors();

    $budi = User::firstWhere('username', 'budi');

    expect($budi->getRoleNames()->all())->toBe(['admin_ga'])
        ->and(Hash::check('Kantor2026aman', $budi->password))->toBeTrue();

    livewire(ManageUsers::class)
        ->callAction(TestAction::make('edit')->table($budi), data: ['role' => Role::findByName('admin_it')->id])
        ->assertHasNoActionErrors();

    expect($budi->refresh()->getRoleNames()->all())->toBe(['admin_it']);
});

test('a new user needs a password that meets the policy', function () {
    $this->actingAs($this->superAdmin);

    livewire(ManageUsers::class)
        ->callAction(CreateAction::class, data: [
            'name' => 'Budi Santoso',
            'username' => 'budi',
            'company_id' => $this->company->id,
            'role' => Role::findByName('karyawan')->id,
            'password' => '12345678',
        ])
        ->assertHasActionErrors(['password']);
});

test('super admin cannot deactivate their own account', function () {
    $this->actingAs($this->superAdmin);

    livewire(ManageUsers::class)
        ->callAction(TestAction::make('edit')->table($this->superAdmin), data: ['is_active' => false]);

    expect($this->superAdmin->refresh()->is_active)->toBeTrue();
});

test('users have no delete action', function () {
    $this->actingAs($this->superAdmin);

    livewire(ManageUsers::class)
        ->assertActionDoesNotExist(TestAction::make('delete')->table(userWithRole('karyawan', 'karyawan')));
});

test('super admin can reset a password and reset 2FA', function () {
    $this->actingAs($this->superAdmin);
    $budi = userWithRole('admin_it', attributes: ['company_id' => $this->company->id, 'app_authentication_recovery_codes' => ['a', 'b']]);

    livewire(ManageUsers::class)
        ->callAction(TestAction::make('resetPassword')->table($budi), data: ['password' => 'Gudang2026baru', 'passwordConfirmation' => 'Gudang2026baru'])
        ->assertHasNoActionErrors()
        ->callAction(TestAction::make('resetTwoFactor')->table($budi));

    $budi->refresh();

    expect(Hash::check('Gudang2026baru', $budi->password))->toBeTrue()
        ->and($budi->getAppAuthenticationSecret())->toBeNull()
        ->and($budi->getAppAuthenticationRecoveryCodes())->toBeNull();
});

test('super admin creates a company and its code is stored in capitals', function () {
    $this->actingAs($this->superAdmin);

    livewire(ManageCompanies::class)
        ->callAction(CreateAction::class, data: ['nama' => 'Tangcity Mall', 'kode' => 'tcm2', 'is_active' => true])
        ->assertHasNoActionErrors();

    expect(Company::firstWhere('nama', 'Tangcity Mall')->kode)->toBe('TCM2');

    livewire(ManageCompanies::class)
        ->callAction(CreateAction::class, data: ['nama' => 'Tangcity Lain', 'kode' => 'TCM2'])
        ->assertHasActionErrors(['kode' => 'unique']);
});
