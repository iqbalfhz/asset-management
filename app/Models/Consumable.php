<?php

namespace App\Models;

use App\Enums\StockMovement;
use App\Models\Concerns\LogsChanges;
use App\Models\Scopes\CompanyScope;
use App\Models\Scopes\ManagedRoleScope;
use Database\Factories\ConsumableFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use LogicException;

#[Fillable(['company_id', 'role_id', 'nama', 'satuan', 'stok_minimum', 'catatan'])]
#[ScopedBy([CompanyScope::class, ManagedRoleScope::class])]
class Consumable extends Model
{
    /** @use HasFactory<ConsumableFactory> */
    use HasFactory, LogsChanges;

    // Stok hanya boleh berubah lewat receive() dan issue(), bukan lewat form atau update biasa.
    private bool $stockChangeAllowed = false;

    protected static function booted(): void
    {
        static::updating(function (Consumable $consumable): void {
            if ($consumable->isDirty('stok') && ! $consumable->stockChangeAllowed) {
                throw new LogicException(__('Stok hanya berubah lewat transaksi masuk atau keluar.'));
            }
        });

        static::deleting(function (Consumable $consumable): void {
            if ($consumable->transactions()->withoutGlobalScopes()->exists()) {
                throw new LogicException(__('Barang yang sudah punya transaksi tidak bisa dihapus.'));
            }
        });
    }

    /**
     * Stok masuk, misalnya dari pembelian.
     *
     * @param  array{supplier_id?: ?int, tanggal?: ?string, catatan?: ?string}  $data
     */
    public function receive(int $jumlah, array $data = []): ConsumableTransaction
    {
        return $this->move(StockMovement::Masuk, $jumlah, $data);
    }

    /**
     * Stok keluar ke karyawan; ditolak kalau melebihi stok.
     *
     * @param  array{tanggal?: ?string, catatan?: ?string}  $data
     */
    public function issue(int $jumlah, Employee $penerima, array $data = []): ConsumableTransaction
    {
        if (! $penerima->is_active) {
            throw new LogicException(__('Karyawan :nama sudah nonaktif.', ['nama' => $penerima->nama]));
        }

        if ((int) $penerima->company_id !== (int) $this->company_id) {
            throw new LogicException(__(':nama berada di perusahaan lain.', ['nama' => $penerima->nama]));
        }

        return $this->move(StockMovement::Keluar, $jumlah, ['employee_id' => $penerima->getKey()] + $data);
    }

    public function isLow(): bool
    {
        return $this->stok_minimum > 0 && $this->stok <= $this->stok_minimum;
    }

    // Barang yang dipantau (minimum > 0) dengan stok sama dengan atau di bawah minimum.
    #[Scope]
    protected function lowStock(Builder $query): void
    {
        $query->where('stok_minimum', '>', 0)->whereColumn('stok', '<=', 'stok_minimum');
    }

    /**
     * @param  array{employee_id?: int, supplier_id?: ?int, tanggal?: ?string, catatan?: ?string}  $data
     */
    private function move(StockMovement $jenis, int $jumlah, array $data): ConsumableTransaction
    {
        if ($jumlah < 1) {
            throw new LogicException(__('Jumlah minimal 1.'));
        }

        // Baris barang dikunci, supaya dua transaksi bersamaan tidak membuat stok minus.
        $transaction = DB::transaction(function () use ($jenis, $jumlah, $data): ConsumableTransaction {
            $item = static::withoutGlobalScopes()->whereKey($this->getKey())->lockForUpdate()->firstOrFail();

            if ($jenis === StockMovement::Keluar && $jumlah > $item->stok) {
                throw new LogicException(__('Stok :nama tinggal :stok :satuan.', ['nama' => $item->nama, 'stok' => $item->stok, 'satuan' => $item->satuan]));
            }

            $item->stockChangeAllowed = true;

            try {
                $item->forceFill(['stok' => $jenis === StockMovement::Masuk ? $item->stok + $jumlah : $item->stok - $jumlah])->save();
            } finally {
                $item->stockChangeAllowed = false;
            }

            return ConsumableTransaction::query()->create([
                'consumable_id' => $item->getKey(),
                'jenis' => $jenis,
                'jumlah' => $jumlah,
                'stok_sesudah' => $item->stok,
                'employee_id' => $data['employee_id'] ?? null,
                'supplier_id' => $data['supplier_id'] ?? null,
                'tanggal' => $data['tanggal'] ?? today()->toDateString(),
                'catatan' => $data['catatan'] ?? null,
                'dibuat_oleh' => auth()->id(),
            ]);
        });

        $this->refresh();

        return $transaction;
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Role admin yang mengelola barang ini.
     *
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * @return HasMany<ConsumableTransaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(ConsumableTransaction::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'stok' => 'integer',
            'stok_minimum' => 'integer',
        ];
    }
}
