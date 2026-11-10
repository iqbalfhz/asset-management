<?php

namespace Database\Seeders;

use App\Enums\StatusType;
use App\Models\Status;
use Illuminate\Database\Seeder;

class StatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $coreStatuses = [
            'Disiapkan' => [StatusType::Disiapkan, '#b7791f'],
            'Tersedia' => [StatusType::SiapDipakai, '#2f6b55'],
            'Dipakai' => [StatusType::SiapDipakai, '#3b5b8a'],
            'Dalam Perbaikan' => [StatusType::TidakBisaDipakai, '#c26a2b'],
            'Rusak' => [StatusType::TidakBisaDipakai, '#b4432f'],
            'Hilang' => [StatusType::Arsip, '#6b6b5e'],
            'Dihapuskan' => [StatusType::Arsip, '#45453c'],
        ];

        foreach ($coreStatuses as $nama => [$tipe, $warna]) {
            Status::firstOrCreate(['nama' => $nama], ['tipe' => $tipe, 'warna' => $warna])
                ->forceFill(['is_core' => true])
                ->save();
        }
    }
}
