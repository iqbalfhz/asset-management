<?php

namespace App\Filament\Schemas\Components;

use App\Models\Scopes\ManagedCategoryScope;
use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class ManagingRoleSelect
{
    // Role pengelola untuk data yang punya role_id; admin yang dibatasi hanya bisa memilih role-nya sendiri.
    public static function make(): Select
    {
        return Select::make('role_id')
            ->label(__('Role pengelola'))
            ->relationship('role', 'name', modifyQueryUsing: fn (Builder $query): Builder => $query
                ->where('panel', 'admin')
                ->when(ManagedCategoryScope::restrictedRoleIds(), fn (Builder $query, array $roleIds): Builder => $query->whereKey($roleIds)))
            ->getOptionLabelFromRecordUsing(fn ($record): string => Str::headline($record->name))
            ->default(fn (): ?int => ManagedCategoryScope::restrictedRoleIds()[0] ?? null)
            ->required();
    }
}
