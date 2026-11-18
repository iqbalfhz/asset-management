<?php

namespace App\Filament\Exports;

use App\Filament\Exports\Concerns\DescribesExportResult;
use App\Models\Consumable;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class ConsumableExporter extends Exporter
{
    use DescribesExportResult;

    protected static ?string $model = Consumable::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('nama')
                ->label(__('Nama')),
            ExportColumn::make('satuan')
                ->label(__('Satuan')),
            ExportColumn::make('stok')
                ->label(__('Stok')),
            ExportColumn::make('stok_minimum')
                ->label(__('Batas minimum')),
            ExportColumn::make('menipis')
                ->label(__('Stok menipis'))
                ->state(fn (Consumable $record): string => $record->isLow() ? __('ya') : __('tidak')),
            ExportColumn::make('role.name')
                ->label(__('Role pengelola'))
                ->formatStateUsing(fn (string $state): string => Str::headline($state)),
            ExportColumn::make('company.kode')
                ->label(__('Perusahaan')),
            ExportColumn::make('catatan')
                ->label(__('Catatan')),
        ];
    }

    public static function modifyQuery(Builder $query): Builder
    {
        return $query->with(['company', 'role']);
    }

    public static function getCompletedNotificationTitle(Export $export): string
    {
        return $export->user->inOwnLanguage(fn (): string => __('Export stok barang selesai'));
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return static::exportResult($export, fn (): string => __('barang'));
    }
}
