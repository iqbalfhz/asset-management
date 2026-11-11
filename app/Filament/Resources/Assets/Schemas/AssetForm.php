<?php

namespace App\Filament\Resources\Assets\Schemas;

use App\Filament\Schemas\Components\CompanySelect;
use App\Models\AssetModel;
use App\Models\Category;
use App\Models\Room;
use App\Models\Status;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class AssetForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Identitas'))
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        CompanySelect::make(),
                        TextInput::make('nama')
                            ->required()
                            ->maxLength(255),
                        Select::make('category_id')
                            ->label(__('Kategori'))
                            ->relationship('category', 'nama', modifyQueryUsing: fn (Builder $query): Builder => $query->managedByCurrentUser())
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Set $set, Get $get, ?string $state): void {
                                $set('asset_model_id', null);
                                $keys = Category::query()->whereKey($state)->value('template_spesifikasi') ?? [];
                                $set('spesifikasi', array_merge(array_fill_keys($keys, ''), $get('spesifikasi') ?? []));
                            }),
                        Select::make('asset_model_id')
                            ->label(__('Model aset'))
                            ->helperText(__('Boleh kosong untuk barang tanpa model, misalnya meja.'))
                            ->relationship('assetModel', 'nama', modifyQueryUsing: fn (Builder $query, Get $get): Builder => $query->with('manufacturer')->where('category_id', $get('category_id')))
                            ->getOptionLabelFromRecordUsing(fn (AssetModel $record): string => "{$record->manufacturer->nama} {$record->nama}")
                            ->searchable()
                            ->preload()
                            ->live(),
                        Select::make('status_id')
                            ->label(__('Status awal'))
                            ->options(fn (): array => Status::query()->whereIn('nama', [Status::DISIAPKAN, Status::TERSEDIA])->pluck('nama', 'id')->all())
                            ->default(fn (): ?int => Status::query()->where('nama', Status::DISIAPKAN)->value('id'))
                            ->required()
                            ->visibleOn('create'),
                        TextInput::make('jumlah')
                            ->label(__('Jumlah'))
                            ->helperText(__('Lebih dari 1 untuk membuat beberapa aset sekaligus dengan kode berurutan.'))
                            ->integer()
                            ->minValue(1)
                            ->maxValue(100)
                            ->default(1)
                            ->required()
                            ->live(onBlur: true)
                            ->visibleOn('create'),
                        TextInput::make('no_seri')
                            ->label(__('Nomor seri'))
                            ->maxLength(255)
                            ->required(fn (Get $get): bool => static::requiresSerialNumber($get))
                            ->hidden(fn (Get $get): bool => (int) $get('jumlah') > 1),
                        Textarea::make('nomor_seri_banyak')
                            ->label(__('Nomor seri, satu per baris'))
                            ->rows(5)
                            ->visible(fn (Get $get, string $operation): bool => $operation === 'create' && (int) $get('jumlah') > 1)
                            ->required(fn (Get $get): bool => static::requiresSerialNumber($get))
                            ->rule(fn (Get $get): Closure => function (string $attribute, ?string $value, Closure $fail) use ($get): void {
                                $count = count(static::serialNumberLines($value));

                                if ($count > 0 && $count !== (int) $get('jumlah')) {
                                    $fail(__('Isi :jumlah nomor seri, satu per baris.', ['jumlah' => (int) $get('jumlah')]));
                                }
                            }),
                        FileUpload::make('foto')
                            ->image()
                            // Tanpa SVG: SVG bisa membawa skrip saat dibuka di browser.
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->directory('assets')
                            ->maxSize(2048),
                    ]),
                Section::make(__('Lokasi'))
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        static::roomSelect('room_id', 'room', __('Lokasi sekarang')),
                        static::roomSelect('default_room_id', 'defaultRoom', __('Lokasi default'))
                            ->helperText(__('Tempat aset kembali saat dikembalikan.')),
                    ]),
                Section::make(__('Pembelian'))
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        Select::make('supplier_id')
                            ->label(__('Supplier'))
                            ->relationship('supplier', 'nama')
                            ->searchable()
                            ->preload(),
                        TextInput::make('order_number')
                            ->label(__('Nomor PO'))
                            ->maxLength(255),
                        DatePicker::make('tgl_beli')
                            ->label(__('Tanggal beli')),
                        TextInput::make('harga_beli')
                            ->label(__('Harga beli'))
                            ->integer()
                            ->minValue(0)
                            ->prefix('Rp'),
                        Select::make('depreciation_id')
                            ->label(__('Kelas penyusutan'))
                            ->helperText(__('Kosongkan untuk mengikuti model aset.'))
                            ->relationship('depreciation', 'nama')
                            ->preload(),
                        DatePicker::make('garansi_sampai')
                            ->label(__('Garansi sampai')),
                        DatePicker::make('eol_date')
                            ->label(__('Tanggal EOL'))
                            ->helperText(__('Kosongkan untuk dihitung dari tanggal beli dan umur pakai model.')),
                    ]),
                Section::make(__('Spesifikasi'))
                    ->columnSpanFull()
                    ->schema([
                        KeyValue::make('spesifikasi')
                            ->hiddenLabel()
                            ->keyLabel(__('Kunci'))
                            ->valueLabel(__('Nilai')),
                    ]),
                Section::make(__('Lainnya'))
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        Toggle::make('is_byod')
                            ->label(__('Perangkat pribadi (BYOD)'))
                            ->helperText(__('Tidak ikut laporan nilai aset dan penyusutan.')),
                        Textarea::make('notes')
                            ->label(__('Catatan'))
                            ->rows(3),
                    ]),
            ]);
    }

    /**
     * @return array<int, string>
     */
    public static function serialNumberLines(?string $value): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $value))));
    }

    private static function requiresSerialNumber(Get $get): bool
    {
        return (bool) AssetModel::query()->whereKey($get('asset_model_id'))->value('wajib_nomor_seri');
    }

    private static function roomSelect(string $name, string $relationship, string $label): Select
    {
        return Select::make($name)
            ->label($label)
            ->relationship($relationship, 'nama', modifyQueryUsing: fn (Builder $query): Builder => $query->with('floor.building'))
            ->getOptionLabelFromRecordUsing(fn (Room $record): string => "{$record->floor->building->nama} — {$record->floor->nama} — {$record->nama}")
            ->searchable()
            ->preload();
    }
}
