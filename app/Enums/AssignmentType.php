<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum AssignmentType: string implements HasLabel
{
    case Keluar = 'keluar';
    case Kembali = 'kembali';

    public function getLabel(): string
    {
        return match ($this) {
            self::Keluar => __('Serah-terima'),
            self::Kembali => __('Pengembalian'),
        };
    }
}
