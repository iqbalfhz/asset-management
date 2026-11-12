<?php

namespace App\Filament\Resources\Consumables\Tables;

use App\Filament\Resources\Consumables\Actions\StockActions;
use App\Models\Consumable;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class ConsumablesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('role'))
            ->defaultSort('nama')
            ->columns([
                TextColumn::make('nama')
                    ->description(fn (Consumable $record): string => Str::headline($record->role->name))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('stok')
                    ->formatStateUsing(fn (int $state, Consumable $record): string => "{$state} {$record->satuan}")
                    ->color(fn (Consumable $record): ?string => $record->isLow() ? 'danger' : null)
                    ->sortable(),
                TextColumn::make('stok_minimum')
                    ->label(__('Minimum'))
                    ->formatStateUsing(fn (int $state, Consumable $record): string => $state > 0 ? "{$state} {$record->satuan}" : '—'),
            ])
            ->filters([
                Filter::make('menipis')
                    ->label(__('Stok menipis'))
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->lowStock()),
            ])
            ->recordActions([
                StockActions::receive(),
                StockActions::issue(),
                ViewAction::make(),
                EditAction::make(),
            ]);
    }
}
