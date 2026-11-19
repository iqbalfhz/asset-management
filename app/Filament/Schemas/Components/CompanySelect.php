<?php

namespace App\Filament\Schemas\Components;

use App\Models\Company;
use App\Models\Scopes\CompanyScope;
use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CompanySelect
{
    // Perusahaan aktif ditambah perusahaan data yang sedang diubah, supaya data lama tetap bisa disimpan; user yang dibatasi hanya melihat perusahaannya.
    public static function make(): Select
    {
        return Select::make('company_id')
            ->label(__('Perusahaan'))
            ->relationship('company', 'nama', modifyQueryUsing: function (Builder $query, ?Model $record): Builder {
                $companyId = CompanyScope::restrictedCompanyId();

                return $query
                    ->when($companyId !== null, fn (Builder $query) => $query->whereKey($companyId))
                    ->where(fn (Builder $query) => $query
                        ->active()
                        ->when($record?->getAttribute('company_id'), fn (Builder $query, int $currentId) => $query->orWhereKey($currentId)));
            })
            ->getOptionLabelFromRecordUsing(fn (Company $record): string => $record->is_active ? $record->nama : __(':nama (nonaktif)', ['nama' => $record->nama]))
            ->default(fn (): ?int => Company::query()->active()->whereKey(auth()->user()?->company_id)->value('id'))
            ->required()
            ->live();
    }
}
