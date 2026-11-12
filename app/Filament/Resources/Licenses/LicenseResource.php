<?php

namespace App\Filament\Resources\Licenses;

use App\Filament\Resources\Licenses\Pages\CreateLicense;
use App\Filament\Resources\Licenses\Pages\EditLicense;
use App\Filament\Resources\Licenses\Pages\ListLicenses;
use App\Filament\Resources\Licenses\Pages\ViewLicense;
use App\Filament\Resources\Licenses\RelationManagers\SeatsRelationManager;
use App\Filament\Resources\Licenses\Schemas\LicenseForm;
use App\Filament\Resources\Licenses\Schemas\LicenseInfolist;
use App\Filament\Resources\Licenses\Tables\LicensesTable;
use App\Models\License;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class LicenseResource extends Resource
{
    protected static ?string $model = License::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static ?int $navigationSort = 6;

    protected static ?string $recordTitleAttribute = 'nama_software';

    public static function getModelLabel(): string
    {
        return __('Lisensi');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Lisensi');
    }

    public static function form(Schema $schema): Schema
    {
        return LicenseForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return LicenseInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LicensesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            SeatsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLicenses::route('/'),
            'create' => CreateLicense::route('/create'),
            'view' => ViewLicense::route('/{record}'),
            'edit' => EditLicense::route('/{record}/edit'),
        ];
    }
}
