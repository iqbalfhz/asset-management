<?php

namespace App\Filament\Widgets;

use App\Enums\AssignmentType;
use App\Models\Assignment;
use App\Models\Maintenance;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

// Beban kerja: serah-terima, pengembalian, dan laporan kerusakan per bulan.
class ActivityChart extends DashboardChart
{
    protected static ?int $sort = 3;

    public ?string $filter = '12';

    public function getHeading(): string
    {
        return __('Aktivitas per bulan');
    }

    public function getDescription(): string
    {
        return __('Jumlah aset yang diserahkan dan dikembalikan, serta laporan kerusakan yang masuk.');
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getFilters(): array
    {
        return [
            '6' => __('6 bulan terakhir'),
            '12' => __('12 bulan terakhir'),
            '24' => __('24 bulan terakhir'),
        ];
    }

    protected function getData(): array
    {
        // Nilai filter bisa diisi bebas lewat request, jadi hanya pilihan yang dikenal yang dipakai.
        $months = match ($this->filter) {
            '6' => 6,
            '24' => 24,
            default => 12,
        };
        $start = today()->startOfMonth()->subMonths($months - 1);
        $periods = collect(range(0, $months - 1))->map(fn (int $offset): CarbonInterface => $start->copy()->addMonths($offset));

        $assignments = fn (AssignmentType $type): Collection => $this->filterByAsset(Assignment::query())
            ->where('jenis', $type)
            ->whereDate('tanggal', '>=', $start)
            ->pluck('tanggal');
        $perMonth = fn (Collection $dates): array => $periods
            ->map(fn (CarbonInterface $month): int => $dates->filter(fn (CarbonInterface $date): bool => $date->isSameMonth($month))->count())
            ->all();

        return [
            'labels' => $periods->map(fn (CarbonInterface $month): string => $month->translatedFormat('M Y'))->all(),
            'datasets' => [
                $this->line(__('Aset diserahkan'), $perMonth($assignments(AssignmentType::Keluar)), self::BLUE),
                $this->line(__('Aset dikembalikan'), $perMonth($assignments(AssignmentType::Kembali)), self::GREEN),
                $this->line(__('Laporan kerusakan'), $perMonth($this->filterByAsset(Maintenance::query())->where('created_at', '>=', $start)->pluck('created_at')), self::BRICK),
            ],
        ];
    }

    protected function getOptions(): array
    {
        return $this->chartOptions(
            ['y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]]],
            ['interaction' => ['mode' => 'index', 'intersect' => false]],
        );
    }
}
