<?php

namespace App\Filament\Resources\Categories;

use App\Filament\Resources\Categories\Pages\ManageCategories;
use App\Models\Category;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?string $recordTitleAttribute = 'nama';

    public static function getModelLabel(): string
    {
        return __('Kategori');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Kategori');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Master Data');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nama')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                TextInput::make('kode')
                    ->helperText(__('Dipakai di kode aset, contoh LPT.'))
                    ->required()
                    ->alphaNum()
                    ->maxLength(5)
                    ->unique(ignoreRecord: true),
                Select::make('role_id')
                    ->label(__('Role pengelola'))
                    ->relationship(
                        'role',
                        'name',
                        modifyQueryUsing: fn (Builder $query): Builder => $query->where('panel', 'admin'),
                    )
                    ->getOptionLabelFromRecordUsing(fn ($record): string => Str::headline($record->name))
                    ->required(),
                ColorPicker::make('warna')
                    ->required(),
                TagsInput::make('template_spesifikasi')
                    ->label(__('Template spesifikasi'))
                    ->helperText(__('Kunci spesifikasi yang diisi untuk aset kategori ini, contoh Prosesor, RAM.'))
                    ->columnSpanFull(),
                Textarea::make('eula')
                    ->label(__('EULA'))
                    ->helperText(__('Syarat penggunaan yang harus diterima karyawan saat menerima aset.'))
                    ->rows(5)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('nama')
            ->columns([
                TextColumn::make('nama')
                    ->badge()
                    ->color(fn (Category $record): ?array => $record->warna ? Color::hex($record->warna) : null)
                    ->searchable(),
                TextColumn::make('kode')
                    ->searchable(),
                TextColumn::make('role.name')
                    ->label(__('Pengelola'))
                    ->formatStateUsing(fn (string $state): string => Str::headline($state)),
                TextColumn::make('template_spesifikasi')
                    ->label(__('Template spesifikasi'))
                    ->badge()
                    ->color('gray')
                    ->limitList(3),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
            'index' => ManageCategories::route('/'),
        ];
    }
}
