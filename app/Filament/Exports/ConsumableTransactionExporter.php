<?php

namespace App\Filament\Exports;

use App\Enums\StockMovement;
use App\Models\ConsumableTransaction;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Filament\Forms\Components\DatePicker;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Number;

class ConsumableTransactionExporter extends Exporter
{
    protected static ?string $model = ConsumableTransaction::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('tanggal')
                ->label(__('Tanggal'))
                ->formatStateUsing(fn (ConsumableTransaction $record): string => $record->tanggal->toDateString()),
            ExportColumn::make('consumable.nama')
                ->label(__('Barang')),
            ExportColumn::make('jenis')
                ->label(__('Jenis'))
                ->formatStateUsing(fn (StockMovement $state): string => $state->getLabel()),
            // Bertanda, supaya bisa langsung dijumlahkan di spreadsheet.
            ExportColumn::make('jumlah')
                ->label(__('Jumlah'))
                ->state(fn (ConsumableTransaction $record): int => $record->jenis->isIncoming() ? $record->jumlah : -$record->jumlah),
            ExportColumn::make('consumable.satuan')
                ->label(__('Satuan')),
            ExportColumn::make('stok_sesudah')
                ->label(__('Stok sesudah')),
            ExportColumn::make('employee.nama')
                ->label(__('Penerima')),
            ExportColumn::make('employee.nik')
                ->label(__('NIK penerima')),
            ExportColumn::make('supplier.nama')
                ->label(__('Supplier')),
            ExportColumn::make('creator.name')
                ->label(__('Dicatat oleh')),
            ExportColumn::make('catatan')
                ->label(__('Catatan')),
        ];
    }

    /**
     * @return array<int, DatePicker>
     */
    public static function getOptionsFormComponents(): array
    {
        return [
            DatePicker::make('dari')
                ->label(__('Dari tanggal'))
                ->default(today()->startOfMonth())
                ->required(),
            DatePicker::make('sampai')
                ->label(__('Sampai tanggal'))
                ->default(today())
                ->afterOrEqual('dari')
                ->required(),
        ];
    }

    public static function modifyQuery(Builder $query): Builder
    {
        return $query->with(['consumable', 'employee', 'supplier', 'creator']);
    }

    public static function getCompletedNotificationTitle(Export $export): string
    {
        return __('Export riwayat stok selesai');
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = __(':count transaksi berhasil diexport.', ['count' => Number::format($export->successful_rows, locale: 'id')]);

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.__(':count baris gagal diexport.', ['count' => Number::format($failedRowsCount, locale: 'id')]);
        }

        return $body;
    }
}
