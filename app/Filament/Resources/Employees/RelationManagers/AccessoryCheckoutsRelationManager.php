<?php

namespace App\Filament\Resources\Employees\RelationManagers;

use App\Filament\Resources\Accessories\AccessoryResource;
use App\Filament\Support\AllocatableStock;
use App\Models\AccessoryCheckout;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AccessoryCheckoutsRelationManager extends RelationManager
{
    protected static string $relationship = 'accessoryCheckouts';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Aksesori');
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('accessory')->open())
            ->defaultSort(AccessoryCheckout::OPENED_AT, 'desc')
            ->recordUrl(fn (AccessoryCheckout $record): string => AccessoryResource::getUrl('view', ['record' => $record->accessory_id]))
            ->emptyStateHeading(__('Tidak memegang aksesori'))
            ->columns([
                TextColumn::make('accessory.nama')
                    ->label(__('Aksesori')),
                TextColumn::make('jumlah'),
                AllocatableStock::openedAt(AccessoryCheckout::class, __('Diserahkan')),
            ])
            ->recordActions([
                AllocatableStock::releaseAction(__('Kembalikan'), __('Aksesori dikembalikan'), 'accessory'),
            ]);
    }
}
