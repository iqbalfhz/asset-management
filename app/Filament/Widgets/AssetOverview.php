<?php

namespace App\Filament\Widgets;

use App\Enums\AssignmentStatus;
use App\Enums\MaintenanceStatus;
use App\Models\Asset;
use App\Models\Assignment;
use App\Models\Maintenance;
use App\Models\Status;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

// Semua angka memakai query yang sudah dibatasi perusahaan dan kategori pengelola.
class AssetOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $perStatus = Asset::query()
            ->join('statuses', 'statuses.id', '=', 'assets.status_id')
            ->whereIn('statuses.nama', [Status::TERSEDIA, Status::DIPAKAI, Status::DALAM_PERBAIKAN])
            ->groupBy('statuses.nama')
            ->selectRaw('statuses.nama as status, count(*) as jumlah')
            ->pluck('jumlah', 'status');

        return [
            Stat::make(__('Tersedia'), (int) ($perStatus[Status::TERSEDIA] ?? 0)),
            Stat::make(__('Dipakai'), (int) ($perStatus[Status::DIPAKAI] ?? 0)),
            Stat::make(__('Dalam perbaikan'), (int) ($perStatus[Status::DALAM_PERBAIKAN] ?? 0)),
            Stat::make(__('Laporan kerusakan baru'), Maintenance::query()->where('status', MaintenanceStatus::Dilaporkan)->count())
                ->description(__('Belum dicek admin')),
            Stat::make(__('Menunggu diterima'), Assignment::query()->where('status', AssignmentStatus::Menunggu)->count())
                ->description(__('Serah-terima ke karyawan')),
            Stat::make(__('Lewat tanggal kembali'), Assignment::query()->returnDueSoon(today()->subDay())->count())
                ->description(__('Pinjaman yang belum dikembalikan')),
            Stat::make(__('Garansi habis'), Asset::query()->warrantyEndingSoon()->count())
                ->description(__('Dalam :hari hari ke depan', ['hari' => Asset::WARRANTY_WARNING_DAYS])),
        ];
    }
}
