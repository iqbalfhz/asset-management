<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum Language: string implements HasLabel
{
    case Indonesia = 'id';
    case English = 'en';

    // Nama bahasa ditulis dalam bahasanya sendiri, supaya dikenali dari bahasa mana pun.
    public function getLabel(): string
    {
        return match ($this) {
            self::Indonesia => 'Bahasa Indonesia',
            self::English => 'English',
        };
    }
}
