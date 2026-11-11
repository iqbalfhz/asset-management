<?php

namespace App\Models;

use App\Enums\MaintenanceStatus;
use App\Models\Concerns\LogsChanges;
use App\Models\Scopes\ManagedCategoryScope;
use Database\Factories\MaintenanceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use LogicException;

#[Fillable([
    'asset_id', 'dilapor_oleh', 'dibuat_oleh', 'status', 'keluhan', 'foto', 'catatan_admin',
    'supplier_id', 'biaya', 'tgl_mulai', 'tgl_selesai', 'hasil', 'status_sebelum_id',
])]
class Maintenance extends Model
{
    /** @use HasFactory<MaintenanceFactory> */
    use HasFactory, LogsChanges;

    public const string FOTO_DISK = 'local';

    protected static function booted(): void
    {
        // Laporan dan perbaikan ikut hak lihat asetnya (perusahaan dan kategori pengelola).
        static::addGlobalScope('asset', fn (Builder $query) => $query->whereHas('asset'));
    }

    /**
     * Karyawan melapor kerusakan aset yang sedang dipegangnya.
     */
    public static function report(Employee $employee, Asset $asset, string $keluhan, ?string $foto = null): self
    {
        return DB::transaction(function () use ($employee, $asset, $keluhan, $foto): self {
            $asset = Asset::withoutGlobalScope(ManagedCategoryScope::class)->with('status')->whereKey($asset->getKey())->lockForUpdate()->firstOrFail();

            $isHolder = $asset->assigned_type === $employee->getMorphClass() && (int) $asset->assigned_id === $employee->getKey();

            if (! $isHolder || $asset->status->nama !== Status::DIPAKAI) {
                throw new LogicException(__('Hanya aset yang sedang Anda pegang yang bisa dilaporkan.'));
            }

            if (static::withoutGlobalScopes()->where('asset_id', $asset->getKey())->whereIn('status', MaintenanceStatus::open())->exists()) {
                throw new LogicException(__('Aset :kode sudah punya laporan yang sedang ditangani.', ['kode' => $asset->kode]));
            }

            return static::query()->create([
                'asset_id' => $asset->getKey(),
                'dilapor_oleh' => $employee->getKey(),
                'dibuat_oleh' => auth()->id(),
                'status' => MaintenanceStatus::Dilaporkan,
                'keluhan' => $keluhan,
                'foto' => $foto,
            ]);
        });
    }

    /**
     * @return BelongsTo<Asset, $this>
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'dilapor_oleh');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Status aset sebelum diperbaiki, untuk dipulihkan saat selesai.
     *
     * @return BelongsTo<Status, $this>
     */
    public function previousStatus(): BelongsTo
    {
        return $this->belongsTo(Status::class, 'status_sebelum_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => MaintenanceStatus::class,
            'biaya' => 'integer',
            'tgl_mulai' => 'date',
            'tgl_selesai' => 'date',
        ];
    }
}
