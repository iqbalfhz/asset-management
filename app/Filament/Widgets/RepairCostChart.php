<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\HasMonthlyPeriod;
use App\Models\Maintenance;

// Biaya perbaikan per bulan; aset mana yang paling mahal ada di tabel Biaya perbaikan tertinggi di sebelahnya.
class RepairCostChart extends DashboardChart
{
    use HasMonthlyPeriod;

    protected static ?int $sort = 6;

    public ?string $filter = '12';

    public function getHeading(): string
    {
        return __('Biaya perbaikan per bulan');
    }

    public function getDescription(): string
    {
        return __('Dalam juta rupiah, menurut tanggal perbaikan selesai.');
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $repairs = $this->filterByAsset(Maintenance::query())
            ->whereNotNull(['biaya', 'tgl_selesai'])
            ->whereDate('tgl_selesai', '>=', $this->months()->first())
            ->get(['tgl_selesai', 'biaya']);

        return [
            'labels' => $this->monthLabels(),
            'datasets' => [
                $this->bars(__('Biaya perbaikan'), array_map(static::millions(...), $this->perMonth($repairs, 'tgl_selesai', 'biaya')), self::BRICK),
            ],
        ];
    }

    protected function getOptions(): array
    {
        return $this->chartOptions($this->monthScales(precision: 1));
    }
}
