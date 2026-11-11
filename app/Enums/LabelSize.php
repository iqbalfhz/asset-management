<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum LabelSize: string implements HasLabel
{
    case Thermal50x25 = 'thermal_50x25';
    case Thermal40x30 = 'thermal_40x30';
    case A4Sheet3x8 = 'a4_3x8';

    public function getLabel(): string
    {
        return match ($this) {
            self::Thermal50x25 => __('Printer thermal, 50 × 25 mm'),
            self::Thermal40x30 => __('Printer thermal, 40 × 30 mm'),
            self::A4Sheet3x8 => __('Stiker A4, 3 × 8 per lembar (70 × 37 mm)'),
        };
    }

    /**
     * Lebar dan tinggi satu label dalam milimeter.
     *
     * @return array{0: int, 1: int}
     */
    public function dimensions(): array
    {
        return match ($this) {
            self::Thermal50x25 => [50, 25],
            self::Thermal40x30 => [40, 30],
            self::A4Sheet3x8 => [70, 37],
        };
    }

    // Thermal: satu label per halaman. A4: label disusun berderet di lembar.
    public function isSheet(): bool
    {
        return $this === self::A4Sheet3x8;
    }
}
