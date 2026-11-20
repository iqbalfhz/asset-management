<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\FiltersByDashboard;
use App\Models\Asset;
use App\Models\Status;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

class DepreciationByClass extends Widget
{
    use FiltersByDashboard;

    protected static ?int $sort = 11;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.depreciation-by-class';

    /**
     * Nilai perolehan dan nilai buku per kelas penyusutan, dari aset yang boleh dilihat user.
     *
     * @return Collection<string, array{jumlah: int, perolehan: int, buku: int}>
     */
    public function rows(): Collection
    {
        $rows = collect();

        $this->filterAssets(Asset::query())
            ->with('depreciation')
            ->whereNotNull('depreciation_id')
            ->whereNotNull('harga_beli')
            ->where('is_byod', false)
            ->whereRelation('status', 'nama', '!=', Status::DIHAPUSKAN)
            ->lazyById()
            ->each(function (Asset $asset) use ($rows): void {
                $row = $rows->get($asset->depreciation->nama, ['jumlah' => 0, 'perolehan' => 0, 'buku' => 0]);

                $rows->put($asset->depreciation->nama, [
                    'jumlah' => $row['jumlah'] + 1,
                    'perolehan' => $row['perolehan'] + $asset->harga_beli,
                    'buku' => $row['buku'] + (int) $asset->bookValue(),
                ]);
            });

        return $rows->sortKeys();
    }
}
