<?php

namespace App\Filament\Resources\Employees\RelationManagers;

use App\Filament\Resources\Consumables\ConsumableResource;
use App\Models\ConsumableTransaction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ConsumableTransactionsRelationManager extends RelationManager
{
    protected static string $relationship = 'consumableTransactions';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Barang habis pakai diterima');
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['consumable', 'creator']))
            ->defaultSort('id', 'desc')
            ->recordUrl(fn (ConsumableTransaction $record): string => ConsumableResource::getUrl('view', ['record' => $record->consumable_id]))
            ->emptyStateHeading(__('Belum pernah menerima barang habis pakai'))
            ->columns([
                TextColumn::make('tanggal')
                    ->date('j M Y'),
                TextColumn::make('consumable.nama')
                    ->label(__('Barang')),
                TextColumn::make('jumlah')
                    ->formatStateUsing(fn (int $state, ConsumableTransaction $record): string => "{$state} {$record->consumable->satuan}"),
                TextColumn::make('creator.name')
                    ->label(__('Dicatat oleh'))
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('catatan')
                    ->limit(50)
                    ->placeholder('—'),
            ]);
    }
}
