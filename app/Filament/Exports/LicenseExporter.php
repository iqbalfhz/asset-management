<?php

namespace App\Filament\Exports;

use App\Models\License;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Number;
use Illuminate\Support\Str;

// Product key sengaja tidak ikut diexport.
class LicenseExporter extends Exporter
{
    protected static ?string $model = License::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('nama_software')
                ->label(__('Software')),
            ExportColumn::make('company.kode')
                ->label(__('Perusahaan')),
            ExportColumn::make('role.name')
                ->label(__('Role pengelola'))
                ->formatStateUsing(fn (string $state): string => Str::headline($state)),
            ExportColumn::make('jumlah_seat')
                ->label(__('Jumlah seat')),
            ExportColumn::make('active_seats_count')
                ->label(__('Seat terpakai'))
                ->counts('activeSeats'),
            ExportColumn::make('tgl_habis')
                ->label(__('Tanggal habis'))
                ->formatStateUsing(fn (License $record): ?string => $record->tgl_habis?->toDateString()),
            ExportColumn::make('harga')
                ->label(__('Harga')),
            ExportColumn::make('supplier.nama')
                ->label(__('Supplier')),
            ExportColumn::make('catatan')
                ->label(__('Catatan')),
        ];
    }

    public static function modifyQuery(Builder $query): Builder
    {
        return $query->with(['company', 'role', 'supplier']);
    }

    public static function getCompletedNotificationTitle(Export $export): string
    {
        return __('Export lisensi selesai');
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = __(':count lisensi berhasil diexport.', ['count' => Number::format($export->successful_rows, locale: 'id')]);

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.__(':count baris gagal diexport.', ['count' => Number::format($failedRowsCount, locale: 'id')]);
        }

        return $body;
    }
}
