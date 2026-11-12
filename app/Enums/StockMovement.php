<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum StockMovement: string implements HasColor, HasLabel
{
    case Masuk = 'masuk';
    case Keluar = 'keluar';
    case PenyesuaianTambah = 'tambah';
    case PenyesuaianKurang = 'kurang';

    public function isIncoming(): bool
    {
        return in_array($this, [self::Masuk, self::PenyesuaianTambah], true);
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Masuk => __('Masuk'),
            self::Keluar => __('Keluar'),
            self::PenyesuaianTambah => __('Penyesuaian (+)'),
            self::PenyesuaianKurang => __('Penyesuaian (−)'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Masuk => 'success',
            self::Keluar => 'gray',
            self::PenyesuaianTambah, self::PenyesuaianKurang => 'warning',
        };
    }
}
