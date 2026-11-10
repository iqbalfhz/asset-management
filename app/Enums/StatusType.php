<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum StatusType: string implements HasLabel
{
    case SiapDipakai = 'siap_dipakai';
    case Disiapkan = 'disiapkan';
    case TidakBisaDipakai = 'tidak_bisa_dipakai';
    case Arsip = 'arsip';

    public function getLabel(): string
    {
        return match ($this) {
            self::SiapDipakai => __('Siap dipakai'),
            self::Disiapkan => __('Disiapkan'),
            self::TidakBisaDipakai => __('Tidak bisa dipakai'),
            self::Arsip => __('Arsip'),
        };
    }
}
