<?php

namespace App\Filament\Widgets;

use App\Models\License;
use Illuminate\Database\Eloquent\Builder;

// Hemat langganan: seat yang belum terpakai tidak perlu diperpanjang.
class LicenseUsageChart extends DashboardChart
{
    protected static ?int $sort = 8;

    public static function canView(): bool
    {
        return auth()->user()?->can('viewAny', License::class) ?? false;
    }

    public function getHeading(): string
    {
        return __('Pemakaian lisensi');
    }

    public function getDescription(): string
    {
        [$used, $unused] = array_map(fn (array $dataset): int => array_sum($dataset['data']), $this->getCachedData()['datasets']);

        if ($used + $unused === 0) {
            return __('Lisensi yang sudah habis tidak dihitung.');
        }

        return __(':kosong dari :total seat belum terpakai. Lisensi yang sudah habis tidak dihitung.', ['kosong' => $unused, 'total' => $used + $unused]);
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $licenses = $this->filterCompany(License::query())
            ->where(fn (Builder $query) => $query->whereNull('tgl_habis')->orWhereDate('tgl_habis', '>=', today()))
            ->withCount('activeSeats')
            ->get();

        $rows = $this->largestOf(
            $licenses->mapWithKeys(fn (License $license): array => [$license->id => [
                'terpakai' => min($license->active_seats_count, $license->jumlah_seat),
                'kosong' => max(0, $license->jumlah_seat - $license->active_seats_count),
            ]]),
            $licenses->pluck('nama_software', 'id'),
        );

        return [
            'labels' => $rows->pluck('label')->all(),
            'datasets' => [
                $this->bars(__('Terpakai'), $rows->pluck('terpakai')->all(), self::BLUE),
                $this->bars(__('Belum terpakai'), $rows->pluck('kosong')->all(), self::GREEN),
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
