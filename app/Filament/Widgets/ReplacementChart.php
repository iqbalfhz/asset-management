<?php

namespace App\Filament\Widgets;

use App\Enums\StatusType;
use App\Models\Asset;
use Illuminate\Support\Collection;

// Anggaran: berapa aset yang habis umur pakainya per tahun, termasuk yang sudah lewat tapi masih dipakai.
class ReplacementChart extends DashboardChart
{
    protected static ?int $sort = 4;

    protected const int YEARS = 5;

    public ?string $filter = 'jumlah';

    public function getHeading(): string
    {
        return __('Aset habis umur pakai');
    }

    public function getDescription(): string
    {
        return __('Menurut tanggal EOL aset. Harga beli dipakai sebagai perkiraan biaya pengganti; aset BYOD tidak dihitung.');
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getFilters(): array
    {
        return [
            'jumlah' => __('Jumlah aset'),
            'nilai' => __('Harga beli (juta rupiah)'),
        ];
    }

    protected function getData(): array
    {
        $firstYear = today()->year;
        $byValue = $this->filter === 'nilai';

        $buckets = $this->filterAssets(Asset::query())
            ->whereNotNull('eol_date')
            ->where('is_byod', false)
            ->whereRelation('status', 'tipe', '!=', StatusType::Arsip->value)
            ->whereDate('eol_date', '<=', today()->setDate($firstYear + static::YEARS - 1, 12, 31))
            ->get(['eol_date', 'harga_beli'])
            ->groupBy(fn (Asset $asset): string => $asset->eol_date->lt(today()) ? 'lewat' : (string) $asset->eol_date->year);

        $keys = collect(['lewat'])->merge(range($firstYear, $firstYear + static::YEARS - 1))->map(fn (int|string $key): string => (string) $key);
        $values = $keys->map(function (string $key) use ($buckets, $byValue): int|float {
            $assets = $buckets->get($key, new Collection);

            return $byValue ? static::millions($assets->sum('harga_beli')) : $assets->count();
        });

        return [
            'labels' => $keys->map(fn (string $key): string => $key === 'lewat' ? __('Sudah lewat') : $key)->all(),
            'datasets' => [
                // Batang "Sudah lewat" merah bata: aset ini seharusnya sudah diganti.
                $this->bars($byValue ? __('Harga beli (juta rupiah)') : __('Jumlah aset'), $values->all(), $keys->map(fn (string $key): string => $key === 'lewat' ? self::BRICK : self::BLUE)->all()),
            ],
        ];
    }

    protected function getOptions(): array
    {
        return $this->chartOptions(['y' => ['beginAtZero' => true, 'ticks' => ['precision' => $this->filter === 'nilai' ? 1 : 0]]]);
    }
}
