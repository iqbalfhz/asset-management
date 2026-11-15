<?php

namespace App\Filament\Exports\Concerns;

use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

trait DescribesExportResult
{
    // Isi notifikasi export selesai: jumlah baris berhasil, ditambah jumlah yang gagal kalau ada.
    protected static function exportResult(Export $export, string $noun): string
    {
        $body = __(':count :data berhasil diexport.', ['count' => Number::format($export->successful_rows, locale: 'id'), 'data' => $noun]);

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.__(':count baris gagal diexport.', ['count' => Number::format($failedRowsCount, locale: 'id')]);
        }

        return $body;
    }
}
