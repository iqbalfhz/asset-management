<?php

namespace App\Filament\Imports\Concerns;

use App\Models\Role;
use App\Models\Scopes\ManagedCategoryScope;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\ImportColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

trait HasManagingRoleColumn
{
    protected static function managingRoleColumn(): ImportColumn
    {
        return ImportColumn::make('role')
            ->label(__('Role pengelola'))
            ->exampleHeader('pengelola')
            ->example('admin_it')
            ->helperText(__('Misalnya admin_it atau Admin IT. Kosong = role akun Anda.'))
            ->guesses(['pengelola', 'role'])
            ->relationship(resolveUsing: fn (string $state): ?Role => static::managingRole($state))
            ->ignoreBlankState();
    }

    // Sama dengan pilihan role di form: hanya role panel admin, dan admin yang dibatasi hanya role-nya sendiri.
    protected static function managingRole(string $name): ?Role
    {
        return Role::query()
            ->where('panel', 'admin')
            ->where('name', Str::of($name)->trim()->lower()->replace(' ', '_')->toString())
            ->when(ManagedCategoryScope::restrictedRoleIds(), fn (Builder $query, array $roleIds): Builder => $query->whereKey($roleIds))
            ->first();
    }

    // Pengelola kosong diisi role akun yang mengimport; super admin (tidak dibatasi) wajib mengisinya.
    protected function fillDefaultManagingRole(): void
    {
        $this->record->role_id ??= ManagedCategoryScope::restrictedRoleIds()[0] ?? null;

        if (blank($this->record->role_id)) {
            throw new RowImportFailedException(__('Isi kolom pengelola.'));
        }
    }
}
