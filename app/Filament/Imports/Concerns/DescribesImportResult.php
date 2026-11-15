<?php

namespace App\Filament\Imports\Concerns;

use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Number;

trait DescribesImportResult
{
    // Isi notifikasi import selesai: jumlah baris berhasil, ditambah jumlah yang gagal kalau ada.
    protected static function importResult(Import $import, string $noun): string
    {
        $body = __(':count :data berhasil diimport.', ['count' => Number::format($import->successful_rows, locale: 'id'), 'data' => $noun]);

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' '.__(':count baris gagal; unduh daftarnya untuk melihat alasannya.', ['count' => Number::format($failedRowsCount, locale: 'id')]);
        }

        return $body;
    }
}
