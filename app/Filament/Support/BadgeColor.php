<?php

namespace App\Filament\Support;

use Filament\Support\Colors\Color;

class BadgeColor
{
    // Warna badge dari kolom warna (hex) kategori atau status; kosong = warna bawaan Filament.
    public static function fromHex(?string $hex): ?array
    {
        return $hex ? Color::hex($hex) : null;
    }
}
