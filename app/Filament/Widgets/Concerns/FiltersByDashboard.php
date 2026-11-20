<?php

namespace App\Filament\Widgets\Concerns;

use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Database\Eloquent\Builder;

// Filter Perusahaan dan Kategori di Dashboard. Global scope hak lihat tetap berlaku, jadi id yang diisi manual tidak membuka data lain.
trait FiltersByDashboard
{
    use InteractsWithPageFilters;

    protected function filterAssets(Builder $assets): Builder
    {
        return $this->filterCompany($assets)
            ->when($this->pageFilter('kategori'), fn (Builder $query, int $categoryId) => $query->where($query->qualifyColumn('category_id'), $categoryId));
    }

    // Data yang menempel ke aset: serah-terima, perbaikan, pengingat.
    protected function filterByAsset(Builder $query): Builder
    {
        if ($this->pageFilter('perusahaan') === null && $this->pageFilter('kategori') === null) {
            return $query;
        }

        return $query->whereHas('asset', fn (Builder $assets) => $this->filterAssets($assets));
    }

    // Lisensi dan barang habis pakai tidak berkategori aset, jadi hanya ikut filter perusahaan.
    protected function filterCompany(Builder $query): Builder
    {
        return $query->when($this->pageFilter('perusahaan'), fn (Builder $query, int $companyId) => $query->where($query->qualifyColumn('company_id'), $companyId));
    }

    private function pageFilter(string $name): ?int
    {
        $value = $this->pageFilters[$name] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }
}
