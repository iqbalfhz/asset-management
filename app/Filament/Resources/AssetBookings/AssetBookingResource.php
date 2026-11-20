<?php

namespace App\Filament\Resources\AssetBookings;

use App\Filament\Resources\AssetBookings\Pages\ListAssetBookings;
use App\Filament\Tables\AssetBookingTable;
use App\Models\AssetBooking;
use BackedEnum;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

// Jadwal pesanan barang bersama; karyawan memesan dari panel karyawan, admin hanya melihat dan membatalkan.
class AssetBookingResource extends Resource
{
    protected static ?string $model = AssetBooking::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static ?int $navigationSort = 3;

    public static function getModelLabel(): string
    {
        return __('Peminjaman');
    }

    public static function getPluralModelLabel(): string
    {
        return trans_choice('Peminjaman|Peminjaman', 2);
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Transaksi');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['asset', 'employee']))
            ->defaultSort('mulai', 'desc')
            ->columns([
                AssetBookingTable::item()
                    ->searchable(),
                TextColumn::make('employee.nama')
                    ->label(__('Pemesan'))
                    ->searchable()
                    ->sortable(),
                AssetBookingTable::period(),
                AssetBookingTable::purpose(),
                AssetBookingTable::status(),
                AssetBookingTable::cancelReason()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('belum_selesai')
                    ->label(__('Hanya yang belum selesai'))
                    ->query(fn (Builder $query): Builder => $query->active()->where('selesai', '>', now()))
                    ->default(),
            ])
            ->recordActions([
                AssetBookingTable::cancelAction()
                    ->authorize('update')
                    ->schema([
                        Textarea::make('alasan_batal')
                            ->label(__('Alasan batal'))
                            ->helperText(__('Terlihat oleh karyawan yang memesan.'))
                            ->rows(2)
                            ->required()
                            ->maxLength(1000),
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAssetBookings::route('/'),
        ];
    }
}
