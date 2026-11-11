<?php

namespace App\Filament\Exports;

use App\Models\Asset;
use App\Models\Assignment;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Number;

class AssetExporter extends Exporter
{
    protected static ?string $model = Asset::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('kode')
                ->label(__('Kode')),
            ExportColumn::make('nama')
                ->label(__('Nama')),
            ExportColumn::make('category.nama')
                ->label(__('Kategori')),
            ExportColumn::make('status.nama')
                ->label(__('Status')),
            ExportColumn::make('lokasi')
                ->label(__('Lokasi'))
                ->state(fn (Asset $record): ?string => $record->room
                    ? "{$record->room->floor->building->nama} — {$record->room->floor->nama} — {$record->room->nama}"
                    : null),
            ExportColumn::make('pemegang')
                ->label(__('Pemegang'))
                ->state(fn (Asset $record): ?string => $record->assigned ? Assignment::labelFor($record->assigned) : null),
            ExportColumn::make('no_seri')
                ->label(__('Nomor seri')),
            ExportColumn::make('order_number')
                ->label(__('Nomor PO')),
            ExportColumn::make('tgl_beli')
                ->label(__('Tanggal beli'))
                ->formatStateUsing(fn (Asset $record): ?string => $record->tgl_beli?->toDateString()),
            ExportColumn::make('harga_beli')
                ->label(__('Harga beli')),
            ExportColumn::make('depreciation.nama')
                ->label(__('Kelas penyusutan')),
            ExportColumn::make('nilai_buku')
                ->label(__('Nilai buku'))
                ->state(fn (Asset $record): ?int => $record->bookValue()),
            ExportColumn::make('garansi_sampai')
                ->label(__('Garansi sampai'))
                ->formatStateUsing(fn (Asset $record): ?string => $record->garansi_sampai?->toDateString()),
            ExportColumn::make('is_byod')
                ->label(__('BYOD'))
                ->formatStateUsing(fn (bool $state): string => $state ? __('ya') : __('tidak')),
        ];
    }

    public static function modifyQuery(Builder $query): Builder
    {
        return $query->with(['room.floor.building', 'assigned', 'depreciation']);
    }

    public static function getCompletedNotificationTitle(Export $export): string
    {
        return __('Export aset selesai');
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = __(':count aset berhasil diexport.', ['count' => Number::format($export->successful_rows, locale: 'id')]);

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.__(':count baris gagal diexport.', ['count' => Number::format($failedRowsCount, locale: 'id')]);
        }

        return $body;
    }
}
