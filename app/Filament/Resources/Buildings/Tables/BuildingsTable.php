<?php

namespace App\Filament\Resources\Buildings\Tables;

use App\Filament\Tables\Columns\CompanyColumn;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BuildingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('nama')
            ->columns([
                TextColumn::make('nama')
                    ->label(__('Nama'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('floors_count')
                    ->label(__('Jumlah lantai'))
                    ->counts('floors')
                    ->sortable(),
                TextColumn::make('manager.nama')
                    ->label(__('Penanggung jawab'))
                    ->sortable(),
                CompanyColumn::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
