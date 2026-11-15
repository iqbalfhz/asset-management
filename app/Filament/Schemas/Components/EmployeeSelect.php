<?php

namespace App\Filament\Schemas\Components;

use App\Models\Employee;
use Closure;
use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Builder;

class EmployeeSelect
{
    // Karyawan aktif di perusahaan yang sama, dicari lewat nama atau NIK.
    public static function make(string $label, int|Closure $companyId): Select
    {
        return Select::make('employee_id')
            ->label($label)
            ->searchable()
            ->getSearchResultsUsing(fn (Select $component, string $search): array => Employee::query()
                ->where('company_id', $component->evaluate($companyId))
                ->where('is_active', true)
                ->where(fn (Builder $query) => $query->where('nama', 'like', "%{$search}%")->orWhere('nik', 'like', "%{$search}%"))
                ->orderBy('nama')
                ->limit(50)
                ->pluck('nama', 'id')
                ->all())
            ->getOptionLabelUsing(fn (int|string $value): ?string => Employee::query()->whereKey($value)->value('nama'))
            ->required();
    }
}
