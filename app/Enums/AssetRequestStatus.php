<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AssetRequestStatus: string implements HasColor, HasLabel
{
    case Diajukan = 'diajukan';
    case Disetujui = 'disetujui';
    case Ditolak = 'ditolak';
    case Selesai = 'selesai';

    public function getLabel(): string
    {
        return match ($this) {
            self::Diajukan => __('Diajukan'),
            self::Disetujui => __('Disetujui'),
            self::Ditolak => __('Ditolak'),
            self::Selesai => __('Selesai'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Diajukan => 'warning',
            self::Disetujui => 'info',
            self::Ditolak => 'gray',
            self::Selesai => 'success',
        };
    }

    /**
     * Permintaan yang masih berjalan; kategori yang sama tidak bisa diminta lagi selama ada yang terbuka.
     *
     * @return array<int, self>
     */
    public static function open(): array
    {
        return [self::Diajukan, self::Disetujui];
    }
}
