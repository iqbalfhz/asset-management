<?php

namespace App\Models;

use App\Enums\AuditResult;
use App\Models\Scopes\ManagedCategoryScope;
use Database\Factories\AuditItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use LogicException;

#[Fillable(['audit_id', 'asset_id', 'hasil', 'ruang_tercatat_id', 'discan_oleh', 'discan_pada', 'dipindahkan_pada'])]
class AuditItem extends Model
{
    /** @use HasFactory<AuditItemFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        // Hasil audit ikut hak lihat asetnya.
        static::addGlobalScope('asset', fn (Builder $query) => $query->whereHas('asset'));
    }

    /**
     * Aset Salah tempat dipindahkan ke ruang audit. Perubahan lokasinya tercatat di log aset.
     */
    public function moveToAuditRoom(): void
    {
        DB::transaction(function (): void {
            $item = $this->lockedSelf();

            if ($item->hasil !== AuditResult::SalahTempat || $item->dipindahkan_pada !== null) {
                throw new LogicException(__('Hanya aset Salah tempat yang belum dipindahkan yang bisa dipindahkan.'));
            }

            $asset = $this->lockedAsset();

            // Lokasi aset yang diserahkan ke ruang atau menempel pada aset lain mengikuti pemegangnya.
            if (in_array($asset->assigned_type, [(new Room)->getMorphClass(), $asset->getMorphClass()], true)) {
                throw new LogicException(__('Aset :kode diserahkan ke ruang atau menempel pada aset lain. Pindahkan lewat pengembalian dan serah-terima.', ['kode' => $asset->kode]));
            }

            $asset->update(['room_id' => Audit::withoutGlobalScopes()->whereKey($item->audit_id)->value('room_id')]);
            $item->update(['dipindahkan_pada' => now()]);
        });

        $this->refresh();
    }

    /**
     * Aset Tidak ditemukan ditandai Hilang; keputusan admin, bukan otomatis saat sesi ditutup.
     */
    public function markLost(): void
    {
        DB::transaction(function (): void {
            if ($this->lockedSelf()->hasil !== AuditResult::TidakDitemukan) {
                throw new LogicException(__('Hanya aset Tidak ditemukan yang bisa ditandai hilang.'));
            }

            $asset = $this->lockedAsset();

            if (! in_array($asset->status->nama, [Status::DISIAPKAN, Status::TERSEDIA, Status::DIPAKAI], true)) {
                throw new LogicException(__('Aset :kode berstatus :status, jadi tidak bisa ditandai hilang.', ['kode' => $asset->kode, 'status' => $asset->status->nama]));
            }

            $asset->transitionTo(Status::named(Status::HILANG));
        });
    }

    private function lockedSelf(): self
    {
        return static::withoutGlobalScopes()->whereKey($this->getKey())->lockForUpdate()->firstOrFail();
    }

    // Aset dibaca tanpa scope kategori: hasil audit sudah lolos hak lihat saat dibuka.
    private function lockedAsset(): Asset
    {
        return Asset::withoutGlobalScope(ManagedCategoryScope::class)->with('status')->whereKey($this->asset_id)->lockForUpdate()->firstOrFail();
    }

    /**
     * @return BelongsTo<Audit, $this>
     */
    public function audit(): BelongsTo
    {
        return $this->belongsTo(Audit::class);
    }

    /**
     * @return BelongsTo<Asset, $this>
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /**
     * Ruang tempat aset tercatat saat discan (atau saat sesi dibuat, kalau belum discan).
     *
     * @return BelongsTo<Room, $this>
     */
    public function recordedRoom(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'ruang_tercatat_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function scanner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'discan_oleh');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'hasil' => AuditResult::class,
            'discan_pada' => 'datetime',
            'dipindahkan_pada' => 'datetime',
        ];
    }
}
