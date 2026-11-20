<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Assets\AssetResource;
use App\Filament\Widgets\Concerns\FiltersByDashboard;
use App\Models\Asset;
use App\Models\Status;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

// Diganti atau diperbaiki lagi: aset yang total biaya perbaikannya mendekati harga belinya.
class CostliestRepairs extends TableWidget
{
    use FiltersByDashboard;

    // Dari persen ini ke atas, mengganti biasanya lebih masuk akal daripada memperbaiki lagi.
    public const int REPLACE_AT_PERCENT = 50;

    protected static ?int $sort = 7;

    public static function canView(): bool
    {
        return auth()->user()?->can('viewAny', Asset::class) ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('Biaya perbaikan tertinggi'))
            ->description(__('Total biaya perbaikan per aset. Merah: sudah :persen% atau lebih dari harga beli, pertimbangkan diganti.', ['persen' => self::REPLACE_AT_PERCENT]))
            ->query($this->filterAssets(Asset::query())
                ->whereHas('maintenances', fn (Builder $query) => $query->whereNotNull('biaya'))
                ->whereRelation('status', 'nama', '!=', Status::DIHAPUSKAN))
            ->defaultSort('maintenances_sum_biaya', 'desc')
            ->paginated([5])
            ->emptyStateHeading(__('Belum ada biaya perbaikan yang dicatat'))
            ->recordUrl(fn (Asset $record): string => AssetResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('kode')
                    ->label(__('Aset'))
                    ->sortable()
                    ->description(fn (Asset $record): string => $record->nama),
                TextColumn::make('maintenances_sum_biaya')
                    ->label(__('Total perbaikan'))
                    ->sum('maintenances', 'biaya')
                    ->money('IDR', locale: 'id')
                    ->sortable(),
                TextColumn::make('harga_beli')
                    ->label(__('Harga beli'))
                    ->money('IDR', locale: 'id')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('persen_harga_beli')
                    ->label(__('Dari harga beli'))
                    ->state(fn (Asset $record): ?int => static::percentOfPrice($record))
                    ->formatStateUsing(fn (int $state): string => "{$state}%")
                    ->placeholder('—')
                    ->color(fn (?int $state): ?string => $state >= self::REPLACE_AT_PERCENT ? 'danger' : null),
            ]);
    }

    public static function percentOfPrice(Asset $asset): ?int
    {
        if (! $asset->harga_beli) {
            return null;
        }

        return (int) round($asset->maintenances_sum_biaya / $asset->harga_beli * 100);
    }
}
