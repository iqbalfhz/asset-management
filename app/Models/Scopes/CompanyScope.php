<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class CompanyScope implements Scope
{
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
        // hasUser() tidak memuat user, jadi membaca user yang login tidak memicu scope ini lagi.
        return Auth::hasUser() ? Auth::user()->restrictedCompanyId() : null;
    }
}
