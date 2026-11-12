<?php

namespace App\Models;

use App\Models\Concerns\LogsChanges;
use App\Models\Scopes\CompanyScope;
use App\Models\Scopes\ManagedCategoryScope;
use Carbon\CarbonInterface;
use Database\Factories\AssetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\DB;
use LogicException;

#[Fillable([
    'company_id', 'nama', 'category_id', 'asset_model_id', 'no_seri', 'foto', 'room_id', 'default_room_id',
    'supplier_id', 'tgl_beli', 'harga_beli', 'depreciation_id', 'order_number', 'garansi_sampai', 'eol_date',
    'spesifikasi', 'is_byod', 'requestable', 'next_audit_date', 'notes',
])]
#[ScopedBy([CompanyScope::class, ManagedCategoryScope::class])]
class Asset extends Model
{
    /** @use HasFactory<AssetFactory> */
    use HasFactory, LogsChanges;

    // Sama dengan default di database, supaya riwayat perubahan tidak mencatat null -> false.
    protected $attributes = [
        'is_byod' => false,
        'requestable' => false,
    ];

    // Status hanya boleh berubah lewat method di model ini, bukan lewat form atau update biasa.
    private bool $statusChangeAllowed = false;

    protected static function booted(): void
    {
        static::creating(function (Asset $asset): void {
            $asset->status_id ??= Status::named(Status::DISIAPKAN)->getKey();

            if (! in_array($asset->status->nama, [Status::DISIAPKAN, Status::TERSEDIA], true)) {
                throw new LogicException(__('Aset baru hanya boleh berstatus Disiapkan atau Tersedia.'));
            }

            $asset->kode = static::nextCode($asset->company_id, $asset->category_id);
        });

        static::saving(function (Asset $asset): void {
            $model = $asset->assetModel;

            if ($model && blank($asset->depreciation_id)) {
                $asset->depreciation_id = $model->depreciation_id;
            }

            if ($model?->eol_bulan && $asset->tgl_beli && blank($asset->eol_date)) {
                $asset->eol_date = $asset->tgl_beli->copy()->addMonths($model->eol_bulan);
            }
        });

        static::updating(function (Asset $asset): void {
            if ($asset->isDirty('kode')) {
                throw new LogicException(__('Kode aset tidak bisa diubah.'));
            }

            if ($asset->isDirty('status_id') && ! $asset->statusChangeAllowed) {
                throw new LogicException(__('Status aset hanya berubah lewat transaksi.'));
            }
        });

        static::deleting(function (): void {
            throw new LogicException(__('Aset tidak dihapus permanen; gunakan proses penghapusan aset.'));
        });
    }

    /**
     * Kode PERUSAHAAN-KATEGORI-TAHUN-NOMOR. Nomor diambil dengan kunci baris supaya dua penyimpanan bersamaan tidak bentrok.
     */
    public static function nextCode(int $companyId, int $categoryId): string
    {
        return DB::transaction(function () use ($companyId, $categoryId): string {
            $key = ['company_id' => $companyId, 'category_id' => $categoryId, 'tahun' => now()->year];

            DB::table('asset_code_sequences')->insertOrIgnore($key + ['nomor_terakhir' => 0]);

            $sequence = DB::table('asset_code_sequences')->where($key)->lockForUpdate()->first();
            $number = $sequence->nomor_terakhir + 1;

            DB::table('asset_code_sequences')->where('id', $sequence->id)->update(['nomor_terakhir' => $number]);

            return sprintf(
                '%s-%s-%d-%05d',
                Company::query()->whereKey($companyId)->value('kode'),
                Category::query()->whereKey($categoryId)->value('kode'),
                $key['tahun'],
                $number,
            );
        });
    }

    /**
     * Fase 2: hanya Disiapkan <-> Tersedia. Status lain berubah lewat transaksi di fase berikutnya.
     */
    public function markAs(Status $target): void
    {
        $preparation = [Status::DISIAPKAN, Status::TERSEDIA];

        if (! in_array($this->status->nama, $preparation, true) || ! in_array($target->nama, $preparation, true)) {
            throw new LogicException(__('Status hanya bisa diubah antara Disiapkan dan Tersedia.'));
        }

        $this->transitionTo($target);
    }

    /**
     * Ubah status beserta kolom terkait. Hanya dipanggil oleh transaksi (persiapan, serah-terima, pengembalian, offboarding).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function transitionTo(Status $status, array $attributes = []): void
    {
        $this->statusChangeAllowed = true;

        try {
            $this->forceFill($attributes)->status()->associate($status)->save();
        } finally {
            $this->statusChangeAllowed = false;
        }
    }

    /**
     * Nilai buku garis lurus: harga beli dikurangi penyusutan per bulan sejak tanggal beli, minimal 0.
     * Null untuk BYOD atau data pembelian yang belum lengkap.
     */
    public function bookValue(?CarbonInterface $at = null): ?int
    {
        $months = $this->depreciation?->jumlah_bulan;

        if ($this->is_byod || $this->harga_beli === null || $this->tgl_beli === null || ! $months) {
            return null;
        }

        $elapsed = min($months, max(0, (int) floor($this->tgl_beli->diffInMonths($at ?? now()))));

        return (int) round($this->harga_beli * ($months - $elapsed) / $months);
    }

    /**
     * Rantai induk aset ini (aset yang ditempeli, induknya, dan seterusnya).
     *
     * @return array<int, int>
     */
    public function parentIds(): array
    {
        $ids = [];
        $current = $this;

        while ($current->assigned_type === $this->getMorphClass() && ! in_array($current->assigned_id, $ids, true)) {
            $ids[] = $current->assigned_id;
            $current = static::withoutGlobalScopes()->find($current->assigned_id);

            if ($current === null) {
                break;
            }
        }

        return $ids;
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsTo<AssetModel, $this>
     */
    public function assetModel(): BelongsTo
    {
        return $this->belongsTo(AssetModel::class);
    }

    /**
     * @return BelongsTo<Status, $this>
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }

    /**
     * @return BelongsTo<Room, $this>
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * @return BelongsTo<Room, $this>
     */
    public function defaultRoom(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'default_room_id');
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return BelongsTo<Depreciation, $this>
     */
    public function depreciation(): BelongsTo
    {
        return $this->belongsTo(Depreciation::class);
    }

    /**
     * @return MorphMany<Attachment, $this>
     */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    /**
     * Pemegang aset: karyawan, ruang, departemen, atau aset lain.
     *
     * @return MorphTo<Model, $this>
     */
    public function assigned(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Aset lain yang menempel pada aset ini (contoh: monitor pada PC).
     *
     * @return MorphMany<Asset, $this>
     */
    public function attachedAssets(): MorphMany
    {
        return $this->morphMany(Asset::class, 'assigned');
    }

    /**
     * @return HasMany<Assignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    /**
     * @return HasMany<Maintenance, $this>
     */
    public function maintenances(): HasMany
    {
        return $this->hasMany(Maintenance::class);
    }

    /**
     * @return HasMany<LicenseSeat, $this>
     */
    public function licenseSeats(): HasMany
    {
        return $this->hasMany(LicenseSeat::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tgl_beli' => 'date',
            'harga_beli' => 'integer',
            'garansi_sampai' => 'date',
            'eol_date' => 'date',
            'spesifikasi' => 'array',
            'is_byod' => 'boolean',
            'requestable' => 'boolean',
            'next_audit_date' => 'date',
        ];
    }
}
