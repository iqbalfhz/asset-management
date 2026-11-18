<?php

namespace App\Filament\Resources\Accessories;

use App\Filament\Resources\Accessories\Pages\ListAccessories;
use App\Filament\Resources\Accessories\Pages\ViewAccessory;
use App\Filament\Resources\Accessories\RelationManagers\CheckoutsRelationManager;
use App\Filament\Support\AllocatableStock;
use App\Models\Accessory;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

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
        return $schema->components([
            Section::make(__('Aksesori'))
                ->columns(3)
                ->columnSpanFull()
                ->schema(AllocatableStock::infolistEntries()),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['role', 'manufacturer', 'company']))
            ->defaultSort('nama')
            ->columns(AllocatableStock::tableColumns())
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ]);
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
