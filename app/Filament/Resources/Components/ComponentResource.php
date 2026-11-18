<?php

namespace App\Filament\Resources\Components;

use App\Filament\Resources\Components\Pages\ListComponents;
use App\Filament\Resources\Components\Pages\ViewComponent;
use App\Filament\Resources\Components\RelationManagers\InstallationsRelationManager;
use App\Filament\Support\AllocatableStock;
use App\Models\Component;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ComponentResource extends Resource
{
    protected static ?string $model = Component::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCpuChip;

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'nama';

    public static function getModelLabel(): string
    {
        return __('Komponen');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Komponen');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components(AllocatableStock::formFields());
    }

    public static function infolist(Schema $schema): Schema
    {
        return AllocatableStock::infolist($schema, __('Komponen'));
    }

    public static function table(Table $table): Table
    {
        return AllocatableStock::table($table);
    }

    public static function getRelations(): array
    {
        return [
            InstallationsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListComponents::route('/'),
            'view' => ViewComponent::route('/{record}'),
        ];
    }
}
