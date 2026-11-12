<?php

namespace App\Models;

use App\Enums\StockMovement;
use Database\Factories\ConsumableTransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable(['consumable_id', 'jenis', 'jumlah', 'stok_sesudah', 'employee_id', 'supplier_id', 'tanggal', 'catatan', 'dibuat_oleh'])]
class ConsumableTransaction extends Model
{
    /** @use HasFactory<ConsumableTransactionFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        // Transaksi ikut hak lihat barangnya.
        static::addGlobalScope('consumable', fn (Builder $query) => $query->whereHas('consumable'));

        // Riwayat stok tidak diubah atau dihapus; salah catat dikoreksi dengan transaksi baru.
        static::updating(fn () => throw new LogicException(__('Transaksi stok tidak bisa diubah.')));
        static::deleting(fn () => throw new LogicException(__('Transaksi stok tidak bisa dihapus.')));
    }

    /**
     * @return BelongsTo<Consumable, $this>
     */
    public function consumable(): BelongsTo
    {
        return $this->belongsTo(Consumable::class);
    }

    /**
     * Penerima barang keluar; dibaca tanpa scope karena transaksi sudah lolos hak lihat lewat barangnya.
     *
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class)->withoutGlobalScopes();
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'jenis' => StockMovement::class,
            'jumlah' => 'integer',
            'stok_sesudah' => 'integer',
            'tanggal' => 'date',
        ];
    }
}
