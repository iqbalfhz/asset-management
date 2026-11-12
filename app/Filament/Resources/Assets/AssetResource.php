<?php

namespace App\Filament\Resources\Assets;

use App\Filament\Resources\Assets\Pages\CreateAsset;
use App\Filament\Resources\Assets\Pages\EditAsset;
use App\Filament\Resources\Assets\Pages\ListAssets;
use App\Filament\Resources\Assets\Pages\ViewAsset;
use App\Filament\Resources\Assets\RelationManagers\ActivitiesRelationManager;
use App\Filament\Resources\Assets\RelationManagers\AssignmentsRelationManager;
use App\Filament\Resources\Assets\RelationManagers\AttachmentsRelationManager;
use App\Filament\Resources\Assets\RelationManagers\LicenseSeatsRelationManager;
use App\Filament\Resources\Assets\RelationManagers\MaintenancesRelationManager;
use App\Filament\Resources\Assets\Schemas\AssetForm;
use App\Filament\Resources\Assets\Schemas\AssetInfolist;
use App\Filament\Resources\Assets\Tables\AssetsTable;
use App\Models\Asset;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class AssetResource extends Resource
{
    protected static ?string $model = Asset::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'kode';

    public static function getModelLabel(): string
    {
        return __('Aset');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Aset');
    }

    public static function form(Schema $schema): Schema
    {
        return AssetForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return AssetInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AssetsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            AttachmentsRelationManager::class,
            AssignmentsRelationManager::class,
            MaintenancesRelationManager::class,
            LicenseSeatsRelationManager::class,
            ActivitiesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAssets::route('/'),
            'create' => CreateAsset::route('/create'),
            'view' => ViewAsset::route('/{record}'),
            'edit' => EditAsset::route('/{record}/edit'),
        ];
    }
}
