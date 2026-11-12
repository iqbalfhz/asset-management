<?php

namespace App\Filament\Exports;

use App\Enums\AuditResult;
use App\Models\AuditItem;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Number;

class AuditItemExporter extends Exporter
{
    protected static ?string $model = AuditItem::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('asset.kode')
                ->label(__('Kode')),
            ExportColumn::make('asset.nama')
                ->label(__('Nama')),
            ExportColumn::make('asset.category.nama')
                ->label(__('Kategori')),
            ExportColumn::make('hasil')
                ->label(__('Hasil'))
                ->formatStateUsing(fn (AuditResult $state): string => $state->getLabel()),
            ExportColumn::make('recordedRoom.nama')
                ->label(__('Tercatat di')),
            ExportColumn::make('discan_pada')
                ->label(__('Discan'))
                ->formatStateUsing(fn (AuditItem $record): ?string => $record->discan_pada?->format('Y-m-d H:i')),
            ExportColumn::make('scanner.name')
                ->label(__('Discan oleh')),
            ExportColumn::make('dipindahkan_pada')
                ->label(__('Lokasi diperbarui'))
                ->formatStateUsing(fn (AuditItem $record): ?string => $record->dipindahkan_pada?->format('Y-m-d H:i')),
        ];
    }

    public static function modifyQuery(Builder $query): Builder
    {
        return $query->with(['asset.category', 'recordedRoom', 'scanner']);
    }

    public static function getCompletedNotificationTitle(Export $export): string
    {
        return __('Export hasil stock opname selesai');
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
