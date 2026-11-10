<?php

namespace App\Models\Scopes;

use App\Models\Setting;
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
        $companyId = static::restrictedCompanyId();

        if ($companyId !== null) {
            $builder->where($model->qualifyColumn('company_id'), $companyId);
        }
    }

    /**
     * Perusahaan yang boleh dilihat user yang login, atau null kalau datanya tidak dibatasi.
     */
    public static function restrictedCompanyId(): ?int
    {
        // hasUser() does not load the user, so resolving the logged-in user never re-enters this scope.
        if (! Auth::hasUser() || ! Setting::get('company_separation', false)) {
            return null;
        }

        $user = Auth::user();

        return $user->can('view_all_companies') ? null : $user->company_id;
    }
}
