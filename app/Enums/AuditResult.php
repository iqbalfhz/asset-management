<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AuditResult: string implements HasColor, HasLabel
{
    case Menunggu = 'menunggu';
    case Ditemukan = 'ditemukan';
    case SalahTempat = 'salah_tempat';
    case TidakDitemukan = 'tidak_ditemukan';

    public function getLabel(): string
    {
        return match ($this) {
            self::Menunggu => __('Belum discan'),
            self::Ditemukan => __('Ditemukan'),
            self::SalahTempat => __('Salah tempat'),
            self::TidakDitemukan => __('Tidak ditemukan'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Menunggu => 'gray',
            self::Ditemukan => 'success',
            self::SalahTempat => 'warning',
            self::TidakDitemukan => 'danger',
        };
    }

    /**
     * Hasil yang berarti asetnya ada secara fisik.
     *
     * @return array<int, self>
     */
    public static function found(): array
    {
        return [self::Ditemukan, self::SalahTempat];
    }
}
