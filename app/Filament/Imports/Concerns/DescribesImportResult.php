<?php

namespace App\Filament\Imports\Concerns;

use Closure;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Number;

// Notifikasi import selesai dibuat di antrean, jadi isinya disusun dalam bahasa user yang mengimport (judulnya juga, di tiap importer).
trait DescribesImportResult
{
    /**
     * @param  Closure(): string  $noun
     */
    protected static function importResult(Import $import, Closure $noun): string
    {
        return $import->user->inOwnLanguage(function () use ($import, $noun): string {
            $body = __(':count :data berhasil diimport.', ['count' => Number::format($import->successful_rows, locale: app()->getLocale()), 'data' => $noun()]);

            if ($failedRowsCount = $import->getFailedRowsCount()) {
                $body .= ' '.__(':count baris gagal; unduh daftarnya untuk melihat alasannya.', ['count' => Number::format($failedRowsCount, locale: app()->getLocale())]);
            }

            return $body;
        });
    }
}
