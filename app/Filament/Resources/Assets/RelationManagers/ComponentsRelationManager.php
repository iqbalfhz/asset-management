<?php

namespace App\Filament\Resources\Assets\RelationManagers;

use App\Filament\Resources\Components\ComponentResource;
use App\Filament\Support\AllocatableStock;
use App\Models\ComponentInstallation;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

// Riwayat pemasangan komponen di aset ini; memasang dilakukan dari halaman komponennya.
class ComponentsRelationManager extends RelationManager
{
    protected static string $relationship = 'componentInstallations';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Komponen');
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('component'))
            ->defaultSort(ComponentInstallation::OPENED_AT, 'desc')
            ->recordUrl(fn (ComponentInstallation $record): string => ComponentResource::getUrl('view', ['record' => $record->component_id]))
            ->columns([
                TextColumn::make('component.nama')
                    ->label(__('Komponen')),
                TextColumn::make('jumlah'),
                AllocatableStock::openedAt(ComponentInstallation::class, __('Dipasang')),
                AllocatableStock::releasedAt(ComponentInstallation::class, __('Dilepas')),
            ])
            ->filters([
                AllocatableStock::openOnlyFilter(ComponentInstallation::class),
            ])
            ->recordActions([
                AllocatableStock::releaseAction(__('Lepas'), __('Komponen dilepas'), 'component'),
            ]);
    }
}
