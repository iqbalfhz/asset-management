<?php

namespace App\Filament\Resources\Employees\RelationManagers;

use App\Filament\Resources\Assets\AssetResource;
use App\Filament\Tables\Columns\AssetStatusColumn;
use App\Models\Asset;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AssetsRelationManager extends RelationManager
{
    protected static string $relationship = 'assignedAssets';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Aset yang dipegang');
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['category', 'status']))
            ->defaultSort('kode')
            ->recordUrl(fn (Asset $record): string => AssetResource::getUrl('view', ['record' => $record]))
            ->emptyStateHeading(__('Tidak memegang aset'))
            ->columns([
                TextColumn::make('kode')
                    ->label(__('Kode'))
                    ->sortable()
                    ->searchable(),
                TextColumn::make('nama')
                    ->label(__('Nama'))
                    ->sortable()
                    ->searchable(),
                TextColumn::make('category.nama')
                    ->label(__('Kategori'))
                    ->sortable(),
                AssetStatusColumn::make(),
            ]);
    }
}
