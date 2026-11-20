<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Consumables\ConsumableResource;
use App\Filament\Widgets\Concerns\FiltersByDashboard;
use App\Models\Consumable;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class LowStockConsumables extends TableWidget
{
    use FiltersByDashboard;

    protected static ?int $sort = 10;

    public static function canView(): bool
    {
        return auth()->user()?->can('viewAny', Consumable::class) ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('Stok barang habis pakai menipis'))
            ->query($this->filterCompany(Consumable::query())->lowStock())
            ->defaultSort('stok')
            ->paginated([5])
            ->emptyStateHeading(__('Semua stok masih di atas batas minimum'))
            ->recordUrl(fn (Consumable $record): string => ConsumableResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('nama')
                    ->label(__('Nama'))
                    ->sortable(),
                TextColumn::make('stok')
                    ->label(__('Stok'))
                    ->sortable()
                    ->formatStateUsing(fn (int $state, Consumable $record): string => $record->quantity($state))
                    ->color('danger'),
                TextColumn::make('stok_minimum')
                    ->label(__('Minimum'))
                    ->sortable()
                    ->formatStateUsing(fn (int $state, Consumable $record): string => $record->quantity($state)),
            ]);
    }
}
