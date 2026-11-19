<?php

namespace App\Filament\Exports\Concerns;

use Closure;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

// Notifikasi export selesai dibuat di antrean, jadi isinya disusun dalam bahasa user yang mengexport (judulnya juga, di tiap exporter).
trait DescribesExportResult
{
    /**
     * @param  Closure(): string  $noun
     */
    protected static function exportResult(Export $export, Closure $noun): string
    {
        return $export->user->inOwnLanguage(function () use ($export, $noun): string {
            $body = __(':count :data berhasil diexport.', ['count' => Number::format($export->successful_rows, locale: app()->getLocale()), 'data' => $noun()]);

            if ($failedRowsCount = $export->getFailedRowsCount()) {
                $body .= ' '.__(':count baris gagal diexport.', ['count' => Number::format($failedRowsCount, locale: app()->getLocale())]);
            }

            return $body;
        });
    }
}
