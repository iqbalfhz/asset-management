<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum StockMovement: string implements HasColor, HasLabel
{
    case Masuk = 'masuk';
    case Keluar = 'keluar';

    public function getLabel(): string
    {
        return match ($this) {
            self::Masuk => __('Masuk'),
            self::Keluar => __('Keluar'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Masuk => 'success',
            self::Keluar => 'gray',
        };
    }
}
