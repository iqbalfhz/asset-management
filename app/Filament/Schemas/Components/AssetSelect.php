<?php

namespace App\Filament\Schemas\Components;

use App\Models\Asset;
use App\Models\Assignment;
use App\Models\Status;
use Closure;
use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Builder;

class AssetSelect
{
    public static function make(string $label, int|Closure $companyId): Select
    {
        return Select::make('asset_id')
            ->label($label)
            ->searchable()
            ->getSearchResultsUsing(fn (Select $component, string $search): array => Asset::query()
                ->where('company_id', $component->evaluate($companyId))
                ->whereDoesntHave('status', fn (Builder $query) => $query->where('nama', Status::DIHAPUSKAN))
                ->where(fn (Builder $query) => $query->where('kode', 'like', "%{$search}%")->orWhere('nama', 'like', "%{$search}%"))
                ->orderBy('kode')
                ->limit(50)
                ->get()
                ->mapWithKeys(fn (Asset $asset): array => [$asset->getKey() => Assignment::labelFor($asset)])
                ->all())
            ->getOptionLabelUsing(fn (int|string $value): ?string => Asset::query()->whereKey($value)->value('kode'))
            ->required();
    }
}
