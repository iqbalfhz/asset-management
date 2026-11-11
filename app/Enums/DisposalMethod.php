<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum DisposalMethod: string implements HasLabel
{
    case Dijual = 'dijual';
    case Dibuang = 'dibuang';
    case Dihibahkan = 'dihibahkan';
    case Hilang = 'hilang';

    public function getLabel(): string
    {
        return match ($this) {
            self::Dijual => __('Dijual'),
            self::Dibuang => __('Dibuang'),
            self::Dihibahkan => __('Dihibahkan'),
            self::Hilang => __('Hilang'),
        };
    }
}
