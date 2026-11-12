<?php

namespace App\Filament\Resources\Employees\RelationManagers;

use App\Filament\Resources\Assets\AssetResource;
use App\Models\Asset;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Colors\Color;
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
                    ->searchable(),
                TextColumn::make('nama')
                    ->searchable(),
                TextColumn::make('category.nama')
                    ->label(__('Kategori')),
                TextColumn::make('status.nama')
                    ->label(__('Status'))
                    ->badge()
                    ->color(fn (Asset $record): ?array => $record->status->warna ? Color::hex($record->status->warna) : null),
            ]);
    }
}
