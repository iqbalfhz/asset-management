<?php

namespace App\Enums;

use Carbon\CarbonInterface;
use Filament\Support\Contracts\HasLabel;

// Pilihan ringkasan email di Profil; dikirim jam 07.00 pada hari pertama periodenya.
enum DigestFrequency: string implements HasLabel
{
    case Mati = 'mati';
    case Mingguan = 'mingguan';
    case Bulanan = 'bulanan';
    case Tahunan = 'tahunan';

    public function getLabel(): string
    {
        return match ($this) {
            self::Mati => __('Mati'),
            self::Mingguan => __('Mingguan (setiap Senin)'),
            self::Bulanan => __('Bulanan (setiap tanggal 1)'),
            self::Tahunan => __('Tahunan (setiap 1 Januari)'),
        };
    }

    public function isDueOn(CarbonInterface $day): bool
    {
        return match ($this) {
            self::Mati => false,
            self::Mingguan => $day->isMonday(),
            self::Bulanan => $day->day === 1,
            self::Tahunan => $day->dayOfYear === 1,
        };
    }

    // Akhir periode yang dicakup ringkasan, supaya hal yang jatuh tempo sebelum ringkasan berikutnya ikut tercantum.
    public function periodEnd(CarbonInterface $day): CarbonInterface
    {
        $day = $day->toImmutable();

        return match ($this) {
            self::Mati => $day,
            self::Mingguan => $day->addWeek(),
            self::Bulanan => $day->addMonth(),
            self::Tahunan => $day->addYear(),
        };
    }
}
