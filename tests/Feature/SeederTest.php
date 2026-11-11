<?php

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\CompanySeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;

test('the seeders create the first company, four roles and one account per role', function () {
    config(['app.seed_password' => 'Kantor2026aman']);

    $this->seed();

    expect(Company::pluck('nama', 'kode')->all())->toBe(['TCM' => 'Tangcity'])
        ->and(Role::pluck('panel', 'name')->sortKeys()->all())->toBe([
            'admin_ga' => 'admin',
            'admin_it' => 'admin',
            'karyawan' => 'karyawan',
            'super_admin' => 'admin',
        ]);

    foreach (['superadmin' => 'super_admin', 'adminit' => 'admin_it', 'adminga' => 'admin_ga', 'karyawan' => 'karyawan'] as $username => $role) {
        expect(User::firstWhere('username', $username)->getRoleNames()->all())->toBe([$role]);
    }
});

test('admin IT gets master data permissions but nothing reserved for super admin', function () {
    config(['app.seed_password' => 'Kantor2026aman']);

    $this->seed();

    $permissions = Role::findByName('admin_it')->permissions->pluck('name');

    expect($permissions)->toContain('ViewAny:Category', 'Create:Employee', 'Delete:Supplier', 'ViewAny:Asset', 'Update:Asset')
        ->and($permissions->filter(fn (string $name): bool => str_ends_with($name, ':Role') || str_ends_with($name, ':User') || str_ends_with($name, ':Company')))->toBeEmpty()
        ->and($permissions)->not->toContain('View:Settings', 'view_all_assets', 'view_all_companies');
});

test('the user seeder refuses a missing or weak SEED_PASSWORD', function (?string $password) {
    config(['app.seed_password' => $password]);
    $this->seed([CompanySeeder::class, RoleSeeder::class]);

    expect(fn () => $this->seed(UserSeeder::class))->toThrow(RuntimeException::class);
})->with([null, 'password']);
