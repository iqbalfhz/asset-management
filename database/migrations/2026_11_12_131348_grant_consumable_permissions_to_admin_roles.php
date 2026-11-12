<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    /**
     * Server yang sudah berjalan: izin menu barang habis pakai dibuat dan diberikan ke Admin IT dan GA tanpa menjalankan seeder lagi.
     * Database baru dilewati; di sana RoleSeeder yang memberi izin.
     */
    public function up(): void
    {
        $roles = Role::query()->whereIn('name', ['admin_it', 'admin_ga'])->get();

        if ($roles->isEmpty()) {
            return;
        }

        $actions = ['ViewAny', 'View', 'Create', 'Update', 'Delete', 'DeleteAny', 'Restore', 'ForceDelete', 'ForceDeleteAny', 'RestoreAny', 'Replicate', 'Reorder'];
        $permissions = collect($actions)->map(fn (string $action): Permission => Permission::findOrCreate("{$action}:Consumable", 'web'));

        foreach ($roles as $role) {
            $role->givePermissionTo($permissions);
        }
    }
};
