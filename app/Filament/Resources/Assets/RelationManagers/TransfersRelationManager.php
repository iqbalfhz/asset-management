<?php

namespace App\Filament\Resources\Assets\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

// Asal-usul aset: kodenya tetap berawalan perusahaan asal, jadi perpindahannya dibaca di sini.
class TransfersRelationManager extends RelationManager
{
    protected static string $relationship = 'transfers';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Riwayat perusahaan');
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['fromCompany', 'toCompany', 'toRoom.floor.building', 'createdBy']))
            ->defaultSort('tanggal', 'desc')
            ->emptyStateHeading(__('Belum pernah pindah perusahaan'))
            ->columns([
                TextColumn::make('tanggal')
                    ->label(__('Tanggal'))
                    ->date('j M Y')
                    ->sortable(),
                TextColumn::make('fromCompany.nama')
                    ->label(__('Dari'))
                    ->sortable(),
                TextColumn::make('toCompany.nama')
                    ->label(__('Ke'))
                    ->sortable(),
                TextColumn::make('ruang_tujuan')
                    ->label(__('Ruang tujuan'))
                    ->state(fn (Model $record): string => $record->toRoom->path()),
                TextColumn::make('alasan')
                    ->label(__('Alasan'))
                    ->wrap(),
                TextColumn::make('createdBy.name')
                    ->label(__('Oleh'))
                    ->placeholder('—'),
            ]);
    }
}
