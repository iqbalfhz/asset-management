<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AuditStatus: string implements HasColor, HasLabel
{
    case Berjalan = 'berjalan';
    case Selesai = 'selesai';

    public function getLabel(): string
    {
        return match ($this) {
            self::Berjalan => __('Berjalan'),
            self::Selesai => __('Selesai'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Berjalan => 'warning',
            self::Selesai => 'success',
        };
    }
}
