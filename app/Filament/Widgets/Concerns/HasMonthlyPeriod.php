<?php

namespace App\Filament\Widgets\Concerns;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

// Pilihan periode untuk grafik per bulan; kelas grafiknya mengisi $filter = '12' sebagai bawaan.
// Nilai filter bisa diisi bebas lewat request, jadi hanya pilihan yang dikenal yang dipakai.
trait HasMonthlyPeriod
{
    protected function getFilters(): array
    {
        return [
            '6' => __('6 bulan terakhir'),
            '12' => __('12 bulan terakhir'),
            '24' => __('24 bulan terakhir'),
        ];
    }

    /**
     * Awal tiap bulan dalam periode, dari yang terlama.
     *
     * @return Collection<int, CarbonInterface>
     */
    protected function months(): Collection
    {
        $count = match ($this->filter) {
            '6' => 6,
            '24' => 24,
            default => 12,
        };
        $start = today()->startOfMonth()->subMonths($count - 1);

        return collect(range(0, $count - 1))->map(fn (int $offset): CarbonInterface => $start->copy()->addMonths($offset));
    }

    /**
     * @return array<int, string>
     */
    protected function monthLabels(): array
    {
        return $this->months()->map(fn (CarbonInterface $month): string => $month->translatedFormat('M y'))->all();
    }

    /**
     * Banyaknya data per bulan, atau jumlah $sumColumn kalau diisi.
     *
     * @param  Collection<int, Model>  $rows
     * @return array<int, int|float>
     */
    protected function perMonth(Collection $rows, string $dateColumn, ?string $sumColumn = null): array
    {
        return $this->months()
            ->map(function (CarbonInterface $month) use ($rows, $dateColumn, $sumColumn): int|float {
                $inMonth = $rows->filter(fn (Model $row): bool => $row->{$dateColumn}->isSameMonth($month));

                return $sumColumn === null ? $inMonth->count() : $inMonth->sum($sumColumn);
            })
            ->all();
    }

    /**
     * Label bulan tegak; kalau tidak muat, sebagian dilewati, bukan dimiringkan.
     *
     * @return array<string, mixed>
     */
    protected function monthScales(int $precision = 0): array
    {
        return [
            'x' => ['ticks' => ['maxRotation' => 0, 'autoSkip' => true]],
            'y' => ['beginAtZero' => true, 'ticks' => ['precision' => $precision]],
        ];
    }
}
