<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum LoginResult: string implements HasColor, HasLabel
{
    case Berhasil = 'berhasil';
    case Gagal = 'gagal';
    case Terkunci = 'terkunci';
    case Keluar = 'keluar';

    public function getLabel(): string
    {
        return match ($this) {
            self::Berhasil => __('Berhasil'),
            self::Gagal => __('Gagal'),
            self::Terkunci => __('Terkunci'),
            self::Keluar => __('Keluar'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Berhasil => 'success',
            self::Gagal => 'danger',
            self::Terkunci => 'warning',
            self::Keluar => 'gray',
        };
    }
}
