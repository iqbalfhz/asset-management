<?php

namespace App\Filament\Widgets;

use App\Models\Asset;
use App\Models\Status;
use Illuminate\Support\Collection;

// Keuangan: harga beli dan nilai buku per kategori.
class ValueByCategoryChart extends DashboardChart
{
    protected static ?int $sort = 5;

    public function getHeading(): string
    {
        return __('Nilai aset per kategori');
    }

    public function getDescription(): string
    {
        return __('Dalam juta rupiah. Hanya aset yang punya harga beli, tanggal beli, dan kelas penyusutan; tanpa BYOD dan aset yang dihapuskan.');
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $rows = new Collection;

        $this->filterAssets(Asset::query())
            ->with('depreciation')
            ->whereNotNull(['depreciation_id', 'harga_beli', 'tgl_beli'])
            ->where('is_byod', false)
            ->whereRelation('status', 'nama', '!=', Status::DIHAPUSKAN)
            ->lazyById()
            ->each(function (Asset $asset) use ($rows): void {
                $row = $rows->get($asset->category_id, ['beli' => 0, 'buku' => 0]);

                $rows->put($asset->category_id, ['beli' => $row['beli'] + $asset->harga_beli, 'buku' => $row['buku'] + (int) $asset->bookValue()]);
            });

        $categories = $this->largestCategories($rows);

        return [
            'labels' => $categories->pluck('label')->all(),
            'datasets' => [
                $this->bars(__('Harga beli'), $categories->map(fn (array $row): float => static::millions($row['beli']))->values()->all(), self::BLUE),
                $this->bars(__('Nilai buku'), $categories->map(fn (array $row): float => static::millions($row['buku']))->values()->all(), self::GREEN),
            ],
        ];
    }

    protected function getOptions(): array
    {
        return $this->chartOptions(['x' => ['beginAtZero' => true]], ['indexAxis' => 'y']);
    }
}
