<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use LogicException;

// Barang berjumlah yang keluar ke pemegang lalu kembali (aksesori ke karyawan, komponen ke aset).
// Tersedia = jumlah dikurangi yang sedang keluar. Kolom barang: nama, jumlah, stok_minimum.
trait AllocatesStock
{
    /**
     * Baris keluar barang ini; model barisnya memakai ReleasesAllocation.
     *
     * @return HasMany<Model, $this>
     */
    abstract public function allocations(): HasMany;

    protected static function bootAllocatesStock(): void
    {
        static::updating(function (Model $item): void {
            if ($item->isDirty('jumlah') && $item->jumlah < $item->allocatedQuantity()) {
                throw new LogicException(__('Jumlah tidak bisa di bawah :count yang sedang dipakai.', ['count' => $item->allocatedQuantity()]));
            }
        });

        static::deleting(function (Model $item): void {
            if ($item->allocations()->withoutGlobalScopes()->exists()) {
                throw new LogicException(__(':nama pernah dipakai, jadi tidak bisa dihapus.', ['nama' => $item->nama]));
            }
        });
    }

    public function allocatedQuantity(): int
    {
        return (int) $this->allocations()->withoutGlobalScopes()->open()->sum('jumlah');
    }

    public function availableQuantity(): int
    {
        return $this->jumlah - $this->allocatedQuantity();
    }

    // Minimum 0 berarti tidak dipantau.
    public function isLow(): bool
    {
        return $this->stok_minimum > 0 && $this->availableQuantity() <= $this->stok_minimum;
    }

    /**
     * Keluarkan sejumlah barang. Baris barang dikunci, supaya dua admin tidak memakai sisa terakhir bersamaan.
     *
     * @param  array<string, mixed>  $attributes  kolom pemegang dan catatan di baris keluar
     */
    protected function allocate(int $jumlah, array $attributes): Model
    {
        return DB::transaction(function () use ($jumlah, $attributes): Model {
            $item = static::withoutGlobalScopes()->whereKey($this->getKey())->lockForUpdate()->firstOrFail();

            if ($jumlah < 1) {
                throw new LogicException(__('Jumlah minimal 1.'));
            }

            if ($jumlah > $item->availableQuantity()) {
                throw new LogicException(__(':nama tinggal :jumlah yang tersedia.', ['nama' => $item->nama, 'jumlah' => $item->availableQuantity()]));
            }

            return $item->allocations()->create(['jumlah' => $jumlah] + $attributes);
        });
    }
}
