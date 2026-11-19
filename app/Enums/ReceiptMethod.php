<?php

namespace App\Enums;

enum ReceiptMethod: string
{
    // Karyawan menekan Terima dan tanda tangan di aplikasinya.
    case Aplikasi = 'aplikasi';

    // Karyawan tanda tangan di layar admin saat serah-terima.
    case Layar = 'layar';

    // Karyawan tanda tangan di BAST cetak; admin mengunggah fotonya.
    case Kertas = 'kertas';
}
