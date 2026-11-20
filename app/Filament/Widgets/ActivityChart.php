<?php

namespace App\Filament\Widgets;

use App\Enums\AssignmentType;
use App\Filament\Widgets\Concerns\HasMonthlyPeriod;
use App\Models\Assignment;
use App\Models\Maintenance;
use Illuminate\Support\Collection;

// Beban kerja: serah-terima, pengembalian, dan laporan kerusakan per bulan.
class ActivityChart extends DashboardChart
{
    use HasMonthlyPeriod;

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

    protected function getData(): array
    {
        $start = $this->months()->first();
        $assignments = fn (AssignmentType $type): Collection => $this->filterByAsset(Assignment::query())
            ->where('jenis', $type)
            ->whereDate('tanggal', '>=', $start)
            ->get(['tanggal']);
        $reports = $this->filterByAsset(Maintenance::query())->where('created_at', '>=', $start)->get(['created_at']);

        return [
            'labels' => $this->monthLabels(),
            'datasets' => [
                $this->line(__('Aset diserahkan'), $this->perMonth($assignments(AssignmentType::Keluar), 'tanggal'), self::BLUE),
                $this->line(__('Aset dikembalikan'), $this->perMonth($assignments(AssignmentType::Kembali), 'tanggal'), self::GREEN),
                $this->line(__('Laporan kerusakan'), $this->perMonth($reports, 'created_at'), self::BRICK),
            ],
        ];
    }

    protected function getOptions(): array
    {
        return $this->chartOptions($this->monthScales(), ['interaction' => ['mode' => 'index', 'intersect' => false]]);
    }
}
