<?php

namespace App\Filament\Schemas\Components;

use App\Models\Scopes\CompanyScope;
use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Builder;

class CompanySelect
{
    // Pilihan perusahaan untuk data yang punya company_id; user yang dibatasi hanya melihat perusahaannya.
    public static function make(): Select
    {
        return Select::make('company_id')
            ->label(__('Perusahaan'))
            ->relationship('company', 'nama', modifyQueryUsing: function (Builder $query): Builder {
                $companyId = CompanyScope::restrictedCompanyId();

                return $companyId === null ? $query : $query->whereKey($companyId);
            })
            ->default(fn (): ?int => auth()->user()?->company_id)
            ->required()
            ->live();
    }
}
