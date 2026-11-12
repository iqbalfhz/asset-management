<?php

namespace App\Filament\Resources\Consumables\RelationManagers;

use App\Enums\StockMovement;
use App\Models\ConsumableTransaction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\On;

class TransactionsRelationManager extends RelationManager
{
    protected static string $relationship = 'transactions';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Riwayat stok');
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    // Dimuat ulang setelah aksi Masuk atau Keluar di halaman yang sama.
    #[On('stock-moved')]
    public function refreshAfterStockMoved(): void {}

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['employee', 'supplier', 'creator']))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('tanggal')
                    ->date('j M Y'),
                TextColumn::make('jenis')
                    ->badge(),
                TextColumn::make('jumlah')
                    ->formatStateUsing(fn (int $state, ConsumableTransaction $record): string => ($record->jenis === StockMovement::Masuk ? '+' : '−').$state),
                TextColumn::make('stok_sesudah')
                    ->label(__('Stok sesudah')),
                TextColumn::make('pihak')
                    ->label(__('Penerima / supplier'))
                    ->state(fn (ConsumableTransaction $record): ?string => $record->employee?->nama ?? $record->supplier?->nama)
                    ->placeholder('—'),
                TextColumn::make('creator.name')
                    ->label(__('Dicatat oleh'))
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('catatan')
                    ->limit(50)
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('jenis')
                    ->options(StockMovement::class),
            ]);
    }
}
