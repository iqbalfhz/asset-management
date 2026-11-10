<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Artisan::call('shield:generate', [
            '--all' => true,
            '--option' => 'permissions',
            '--panel' => 'admin',
            '--no-interaction' => true,
        ]);

        foreach (array_keys(config('filament-shield.custom_permissions')) as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $roles = [
            'super_admin' => 'admin',
            'admin_it' => 'admin',
            'admin_ga' => 'admin',
            'karyawan' => 'karyawan',
        ];

        foreach ($roles as $name => $panel) {
            Role::firstOrCreate(['name' => $name, 'guard_name' => 'web'], ['panel' => $panel]);
        }

        // Admin IT dan GA: semua izin menu, kecuali Roles, User, Perusahaan, dan izin lihat semua.
        $adminPermissions = Permission::query()
            ->where('name', 'not like', '%:Role')
            ->where('name', 'not like', '%:User')
            ->where('name', 'not like', '%:Company')
            ->where('name', '!=', 'View:Settings')
            ->whereNotIn('name', ['view_all_assets', 'view_all_companies'])
            ->get();

        Role::findByName('admin_it')->syncPermissions($adminPermissions);
        Role::findByName('admin_ga')->syncPermissions($adminPermissions);
    }
}
