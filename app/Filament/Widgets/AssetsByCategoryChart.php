<?php

namespace App\Filament\Widgets;

use App\Enums\StatusType;
use App\Models\Asset;
use App\Models\Status;

// Pengadaan: kategori mana yang stok tersedianya habis.
class AssetsByCategoryChart extends DashboardChart
{
    protected static ?int $sort = 2;

    public function getHeading(): string
    {
        return __('Aset per kategori');
    }

    public function getDescription(): string
    {
        return __('Batang hijau adalah aset yang siap diserahkan. Aset Hilang dan Dihapuskan tidak dihitung.');
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $rows = $this->filterAssets(Asset::query())
            ->join('statuses', 'statuses.id', '=', 'assets.status_id')
            ->where('statuses.tipe', '!=', StatusType::Arsip->value)
            ->groupBy('assets.category_id', 'statuses.nama', 'statuses.tipe')
            ->selectRaw('assets.category_id, statuses.nama, statuses.tipe, count(*) as jumlah')
            ->get()
            ->groupBy('category_id')
            ->map(function ($statuses): array {
                $groups = ['tersedia' => 0, 'dipakai' => 0, 'tidak_siap' => 0];

                foreach ($statuses as $status) {
                    $groups[match (true) {
                        $status->nama === Status::TERSEDIA => 'tersedia',
                        $status->tipe === StatusType::SiapDipakai->value => 'dipakai',
                        default => 'tidak_siap',
                    }] += (int) $status->jumlah;
                }

                return $groups;
            });

        $categories = $this->largestCategories($rows);

        return [
            'labels' => $categories->pluck('label')->all(),
            'datasets' => [
                $this->bars(__('Tersedia'), $categories->pluck('tersedia')->all(), self::GREEN),
                $this->bars(__('Dipakai'), $categories->pluck('dipakai')->all(), self::BLUE),
                $this->bars(__('Belum atau tidak bisa dipakai'), $categories->pluck('tidak_siap')->all(), self::BRICK),
            ],
        ];
    }

    protected function getOptions(): array
    {
        return $this->chartOptions(
            ['x' => ['stacked' => true, 'beginAtZero' => true, 'ticks' => ['precision' => 0]], 'y' => ['stacked' => true]],
            ['indexAxis' => 'y'],
        );
    }
}
