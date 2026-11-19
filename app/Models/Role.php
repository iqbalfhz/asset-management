<?php

namespace App\Models;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    /**
     * Untuk migrasi di server yang sudah berjalan; database baru dilewati karena RoleSeeder yang memberi izin.
     */
    public static function grantMenuToAdmins(string $model): void
    {
        $roles = static::query()->whereIn('name', ['admin_it', 'admin_ga'])->get();

        if ($roles->isEmpty()) {
            return;
        }

        $actions = ['ViewAny', 'View', 'Create', 'Update', 'Delete', 'DeleteAny', 'Restore', 'ForceDelete', 'ForceDeleteAny', 'RestoreAny', 'Replicate', 'Reorder'];
        $permissions = collect($actions)->map(fn (string $action): Permission => Permission::findOrCreate("{$action}:{$model}", 'web'));

        foreach ($roles as $role) {
            $role->givePermissionTo($permissions);
        }
    }
}
