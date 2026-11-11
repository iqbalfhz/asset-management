<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum MaintenanceStatus: string implements HasColor, HasLabel
{
    case Dilaporkan = 'dilaporkan';
    case Ditolak = 'ditolak';
    case Diproses = 'diproses';
    case Selesai = 'selesai';
    case Rusak = 'rusak';

    public function getLabel(): string
    {
        return match ($this) {
            self::Dilaporkan => __('Dilaporkan'),
            self::Ditolak => __('Ditolak'),
            self::Diproses => __('Diproses'),
            self::Selesai => __('Selesai'),
            self::Rusak => __('Rusak'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Dilaporkan => 'warning',
            self::Ditolak => 'gray',
            self::Diproses => 'info',
            self::Selesai => 'success',
            self::Rusak => 'danger',
        };
    }

    /**
     * Laporan yang masih berjalan; aset yang sama tidak bisa dilapor lagi selama ada yang terbuka.
     *
     * @return array<int, self>
     */
    public static function open(): array
    {
        return [self::Dilaporkan, self::Diproses];
    }
}
