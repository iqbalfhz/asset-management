<?php

namespace App\Filament\Resources\AssetModels;

use App\Filament\Resources\AssetModels\Pages\ManageAssetModels;
use App\Models\AssetModel;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;

class AssetModelResource extends Resource
{
    protected static ?string $model = AssetModel::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedComputerDesktop;

    protected static ?string $recordTitleAttribute = 'nama';

    public static function getModelLabel(): string
    {
        return __('Model Aset');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Model Aset');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Master Data');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('manufacturer_id')
                    ->label(__('Produsen'))
                    ->relationship('manufacturer', 'nama')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live(),
                TextInput::make('nama')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule->where('manufacturer_id', $get('manufacturer_id'))),
                TextInput::make('nomor_model')
                    ->maxLength(255),
                Select::make('category_id')
                    ->label(__('Kategori'))
                    ->relationship('category', 'nama')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('depreciation_id')
                    ->label(__('Kelas penyusutan'))
                    ->relationship('depreciation', 'nama')
                    ->preload(),
                TextInput::make('eol_bulan')
                    ->label(__('Umur pakai (EOL)'))
                    ->helperText(__('Dipakai menghitung tanggal EOL aset dari tanggal belinya.'))
                    ->integer()
                    ->minValue(1)
                    ->suffix(__('bulan')),
                Toggle::make('wajib_nomor_seri')
                    ->label(__('Wajib nomor seri'))
                    ->helperText(__('Setiap aset dengan model ini harus punya nomor seri.')),
                FileUpload::make('foto')
                    ->image()
                    ->directory('asset-models')
                    ->maxSize(2048)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('nama')
            ->columns([
                ImageColumn::make('foto')
                    ->square(),
                TextColumn::make('manufacturer.nama')
                    ->label(__('Produsen'))
                    ->searchable(),
                TextColumn::make('nama')
                    ->searchable(),
                TextColumn::make('nomor_model')
                    ->searchable(),
                TextColumn::make('category.nama')
                    ->label(__('Kategori')),
                TextColumn::make('depreciation.nama')
                    ->label(__('Kelas penyusutan'))
                    ->toggleable(),
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
            'index' => ManageAssetModels::route('/'),
        ];
    }
}
