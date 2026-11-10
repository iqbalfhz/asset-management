<?php

namespace App\Filament\Resources\Rooms;

use App\Filament\Resources\Rooms\Pages\ManageRooms;
use App\Models\Floor;
use App\Models\Room;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;

class RoomResource extends Resource
{
    protected static ?string $model = Room::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static ?string $recordTitleAttribute = 'nama';

    public static function getModelLabel(): string
    {
        return __('Ruang');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Ruang');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Organisasi');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('floor_id')
                    ->label(__('Lantai'))
                    ->relationship('floor', 'nama', modifyQueryUsing: fn (Builder $query): Builder => $query->with('building'))
                    ->getOptionLabelFromRecordUsing(fn (Floor $record): string => "{$record->building->nama} — {$record->nama}")
                    ->preload()
                    ->required()
                    ->live(),
                TextInput::make('nama')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule->where('floor_id', $get('floor_id'))),
                Select::make('manager_id')
                    ->label(__('Penanggung jawab'))
                    ->relationship('manager', 'nama')
                    ->searchable()
                    ->preload(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('nama')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('floor.building'))
            ->columns([
                TextColumn::make('floor.building.nama')
                    ->label(__('Gedung')),
                TextColumn::make('floor.nama')
                    ->label(__('Lantai')),
                TextColumn::make('nama')
                    ->searchable(),
                TextColumn::make('manager.nama')
                    ->label(__('Penanggung jawab')),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageRooms::route('/'),
        ];
    }
}
