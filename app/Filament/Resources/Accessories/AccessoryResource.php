<?php

namespace App\Filament\Resources\Accessories;

use App\Filament\Resources\Accessories\Pages\ListAccessories;
use App\Filament\Resources\Accessories\Pages\ViewAccessory;
use App\Filament\Resources\Accessories\RelationManagers\CheckoutsRelationManager;
use App\Filament\Support\AllocatableStock;
use App\Models\Accessory;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class AccessoryResource extends Resource
{
    protected static ?string $model = Accessory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCursorArrowRays;

    protected static ?int $navigationSort = 9;

    protected static ?string $recordTitleAttribute = 'nama';

    public static function getModelLabel(): string
    {
        return __('Aksesori');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Aksesori');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components(AllocatableStock::formFields());
    }

    public static function infolist(Schema $schema): Schema
    {
        return AllocatableStock::infolist($schema, __('Aksesori'));
    }

    public static function table(Table $table): Table
    {
        return AllocatableStock::table($table);
    }

    public static function getRelations(): array
    {
        return [
            CheckoutsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAccessories::route('/'),
            'view' => ViewAccessory::route('/{record}'),
        ];
    }
}
