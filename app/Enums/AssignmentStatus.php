<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AssignmentStatus: string implements HasColor, HasLabel
{
    case Menunggu = 'menunggu';
    case Diterima = 'diterima';

    public function getLabel(): string
    {
        return match ($this) {
            self::Menunggu => __('Menunggu diterima'),
            self::Diterima => __('Diterima'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Menunggu => 'warning',
            self::Diterima => 'success',
        };
    }
}
