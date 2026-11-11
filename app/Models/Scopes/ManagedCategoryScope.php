<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class ManagedCategoryScope implements Scope
{
    /**
     * Admin hanya melihat aset dari kategori yang dikelola role-nya.
     */
    public function apply(Builder $builder, Model $model): void
    {
        $roleIds = static::restrictedRoleIds();

        if ($roleIds !== null) {
            $builder->whereHas('category', fn (Builder $query) => $query->whereIn('role_id', $roleIds));
        }
    }

    /**
     * Role yang kategorinya boleh dilihat user yang login, atau null kalau tidak dibatasi.
     *
     * @return array<int, int>|null
     */
    public static function restrictedRoleIds(): ?array
    {
        if (! Auth::hasUser() || Auth::user()->can('view_all_assets')) {
            return null;
        }

        return Auth::user()->roles->pluck('id')->all();
    }
}
