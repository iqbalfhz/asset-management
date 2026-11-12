<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class ManagedRoleScope implements Scope
{
    /**
     * Data dengan kolom role_id pengelola: admin hanya melihat milik role-nya, sama seperti kategori aset.
     */
    public function apply(Builder $builder, Model $model): void
    {
        $roleIds = ManagedCategoryScope::restrictedRoleIds();

        if ($roleIds !== null) {
            $builder->whereIn($model->qualifyColumn('role_id'), $roleIds);
        }
    }
}
