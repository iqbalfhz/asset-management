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
     * Admin membuat perbaikan langsung (servis rutin); langsung diproses.
     *
     * @param  array{keluhan: string, supplier_id?: ?int, tgl_mulai?: ?string, catatan_admin?: ?string}  $data
     */
    public static function startDirect(Asset $asset, array $data): self
    {
        return DB::transaction(function () use ($asset, $data): self {
            $asset = Asset::query()->with('status')->whereKey($asset->getKey())->lockForUpdate()->firstOrFail();

            if (static::withoutGlobalScopes()->where('asset_id', $asset->getKey())->whereIn('status', MaintenanceStatus::open())->exists()) {
                throw new LogicException(__('Aset :kode sudah punya perbaikan yang sedang berjalan.', ['kode' => $asset->kode]));
            }

            $maintenance = static::query()->create([
                'asset_id' => $asset->getKey(),
                'dibuat_oleh' => auth()->id(),
                'status' => MaintenanceStatus::Dilaporkan,
                'keluhan' => $data['keluhan'],
            ]);

            $maintenance->process($data);

            return $maintenance;
        });
    }

    /**
     * @param  array{supplier_id?: ?int, tgl_mulai?: ?string, catatan_admin?: ?string}  $data
     */
    public function process(array $data): void
    {
        DB::transaction(function () use ($data): void {
            $this->ensureStatus(MaintenanceStatus::Dilaporkan);
            $asset = $this->lockedAsset();

            if (! in_array($asset->status->nama, [Status::TERSEDIA, Status::DIPAKAI], true)) {
                throw new LogicException(__('Aset :kode berstatus :status, jadi tidak bisa diperbaiki.', ['kode' => $asset->kode, 'status' => $asset->status->nama]));
            }

            $this->update([
                'status' => MaintenanceStatus::Diproses,
                'status_sebelum_id' => $asset->status_id,
                'supplier_id' => $data['supplier_id'] ?? null,
                'tgl_mulai' => $data['tgl_mulai'] ?? now()->toDateString(),
                'catatan_admin' => $data['catatan_admin'] ?? null,
            ]);

            $asset->transitionTo(Status::named(Status::DALAM_PERBAIKAN));
        });
    }

    public function reject(string $catatan): void
    {
        DB::transaction(function () use ($catatan): void {
            $this->ensureStatus(MaintenanceStatus::Dilaporkan);

            $this->update(['status' => MaintenanceStatus::Ditolak, 'catatan_admin' => $catatan]);
        });
    }

    /**
     * Perbaikan berhasil: aset kembali ke status sebelumnya, pemegangnya tetap.
     *
     * @param  array{hasil: string, biaya?: ?int, tgl_selesai?: ?string}  $data
     */
    public function finish(array $data): void
    {
        $this->close(MaintenanceStatus::Selesai, null, $data);
    }

    /**
     * Perbaikan gagal: aset Rusak dan menunggu dihapuskan.
     *
     * @param  array{hasil: string, biaya?: ?int, tgl_selesai?: ?string}  $data
     */
    public function markBroken(array $data): void
    {
        $this->close(MaintenanceStatus::Rusak, Status::named(Status::RUSAK), $data);
    }

    /**
     * @param  ?Status  $assetStatus  status aset setelahnya; null = kembali ke status sebelum diperbaiki
     * @param  array{hasil: string, biaya?: ?int, tgl_selesai?: ?string}  $data
     */
    private function close(MaintenanceStatus $result, ?Status $assetStatus, array $data): void
    {
        DB::transaction(function () use ($result, $assetStatus, $data): void {
            $this->ensureStatus(MaintenanceStatus::Diproses);
            $asset = $this->lockedAsset();
            $assetStatus ??= Status::query()->findOrFail(static::withoutGlobalScopes()->whereKey($this->getKey())->value('status_sebelum_id'));

            $this->update([
                'status' => $result,
                'hasil' => $data['hasil'],
                'biaya' => $data['biaya'] ?? null,
                'tgl_selesai' => $data['tgl_selesai'] ?? now()->toDateString(),
            ]);

            $asset->transitionTo($assetStatus);
        });
    }

    // Dibaca ulang dengan kunci baris, supaya dua admin tidak memproses laporan yang sama bersamaan.
    private function ensureStatus(MaintenanceStatus $expected): void
    {
        /** @var MaintenanceStatus $current */
        $current = static::withoutGlobalScopes()->whereKey($this->getKey())->lockForUpdate()->value('status');

        if ($current !== $expected) {
            throw new LogicException(__('Perbaikan ini berstatus :status, jadi aksi ini tidak bisa dilakukan.', ['status' => $current->getLabel()]));
        }
    }

    // Aset dibaca tanpa scope kategori: perbaikan sudah lolos hak lihat saat dibuka.
    private function lockedAsset(): Asset
    {
        return Asset::withoutGlobalScope(ManagedCategoryScope::class)->with('status')->whereKey($this->asset_id)->lockForUpdate()->firstOrFail();
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
