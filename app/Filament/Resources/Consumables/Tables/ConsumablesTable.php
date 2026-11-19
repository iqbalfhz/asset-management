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
                    ->label(__('Nama'))
                    ->description(fn (Consumable $record): string => Str::headline($record->role->name))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('stok')
                    ->label(__('Stok'))
                    ->formatStateUsing(fn (int $state, Consumable $record): string => $record->quantity($state))
                    ->color(fn (Consumable $record): ?string => $record->isLow() ? 'danger' : null)
                    ->sortable(),
                TextColumn::make('stok_minimum')
                    ->label(__('Minimum'))
                    ->sortable()
                    ->formatStateUsing(fn (int $state, Consumable $record): string => $state > 0 ? $record->quantity($state) : '—'),
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
