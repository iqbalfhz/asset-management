<?php

namespace App\Filament\Resources\Consumables;

use App\Filament\Resources\Consumables\Pages\CreateConsumable;
use App\Filament\Resources\Consumables\Pages\EditConsumable;
use App\Filament\Resources\Consumables\Pages\ListConsumables;
use App\Filament\Resources\Consumables\Pages\ViewConsumable;
use App\Filament\Resources\Consumables\RelationManagers\TransactionsRelationManager;
use App\Filament\Resources\Consumables\Schemas\ConsumableForm;
use App\Filament\Resources\Consumables\Schemas\ConsumableInfolist;
use App\Filament\Resources\Consumables\Tables\ConsumablesTable;
use App\Models\Consumable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ConsumableResource extends Resource
{
    protected static ?string $model = Consumable::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;

    protected static ?int $navigationSort = 7;

    protected static ?string $recordTitleAttribute = 'nama';

    public static function getModelLabel(): string
    {
        return __('Barang habis pakai');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Barang habis pakai');
    }

    public static function form(Schema $schema): Schema
    {
        return ConsumableForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ConsumableInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ConsumablesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            TransactionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListConsumables::route('/'),
            'create' => CreateConsumable::route('/create'),
            'view' => ViewConsumable::route('/{record}'),
            'edit' => EditConsumable::route('/{record}/edit'),
        ];
    }
}
