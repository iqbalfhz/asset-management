<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class CompanyScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        // hasUser() does not load the user, so resolving the logged-in user never re-enters this scope.
        if (! config('app.company_separation') || ! Auth::hasUser()) {
            return;
        }

        $user = Auth::user();

        if ($user->can('view_all_companies')) {
            return;
        }

        $builder->where($model->qualifyColumn('company_id'), $user->company_id);
    }
}
