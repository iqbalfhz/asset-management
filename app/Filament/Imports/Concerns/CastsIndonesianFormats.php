<?php

namespace App\Filament\Imports\Concerns;

use DateTimeImmutable;

trait CastsIndonesianFormats
{
    // Tanggal Indonesia (31/01/2026 atau 31-01-2026) diubah ke Y-m-d; format lain dibiarkan untuk divalidasi.
    protected static function date(?string $state): ?string
    {
        if (blank($state)) {
            return null;
        }

        foreach (['d/m/Y', 'd-m-Y', 'Y-m-d'] as $format) {
            $date = DateTimeImmutable::createFromFormat("!{$format}", $state);

            // Dicocokkan balik supaya tanggal yang tidak ada (31/02/2026) ikut ditolak.
            if ($date !== false && $date->format($format) === $state) {
                return $date->format('Y-m-d');
            }
        }

        return $state;
    }

    // "Rp 12.500.000" atau "12500000,00" menjadi "12500000".
    protected static function rupiah(?string $state): ?string
    {
        if (blank($state)) {
            return null;
        }

        $withoutCents = preg_replace('/[.,]\d{2}$/', '', $state);
        $digits = preg_replace('/[^0-9]/', '', $withoutCents);

        return $digits === '' ? $state : $digits;
    }
}
