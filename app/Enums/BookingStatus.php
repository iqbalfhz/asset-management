<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

// Status pesanan barang bersama, dihitung dari jamnya; tidak disimpan.
enum BookingStatus: string implements HasColor, HasLabel
{
    case AkanDatang = 'akan_datang';
    case Berjalan = 'berjalan';
    case Selesai = 'selesai';
    case Dibatalkan = 'dibatalkan';

    public function getLabel(): string
    {
        return match ($this) {
            self::AkanDatang => __('Akan datang'),
            self::Berjalan => __('Berjalan'),
            self::Selesai => __('Selesai'),
            self::Dibatalkan => __('Dibatalkan'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::AkanDatang => 'info',
            self::Berjalan => 'success',
            self::Selesai => 'gray',
            self::Dibatalkan => 'danger',
        };
    }
}
