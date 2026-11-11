<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

// Bagian yang bisa ditampilkan di label; kode aset selalu tercetak.
enum LabelField: string implements HasLabel
{
    case Perusahaan = 'perusahaan';
    case NamaAset = 'nama';
    case NomorSeri = 'no_seri';
    case Qr = 'qr';
    case Barcode = 'barcode';

    public function getLabel(): string
    {
        return match ($this) {
            self::Perusahaan => __('Nama perusahaan'),
            self::NamaAset => __('Nama aset'),
            self::NomorSeri => __('Nomor seri'),
            self::Qr => __('QR code'),
            self::Barcode => __('Barcode'),
        };
    }

    /**
     * @return array<int, string>
     */
    public static function defaults(): array
    {
        return [self::Perusahaan->value, self::NamaAset->value, self::Qr->value, self::Barcode->value];
    }
}
