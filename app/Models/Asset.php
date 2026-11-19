<?php

namespace App\Models;

use App\Enums\DisposalStatus;
use App\Models\Concerns\LogsChanges;
use App\Models\Scopes\CompanyScope;
use App\Models\Scopes\ManagedCategoryScope;
use Carbon\CarbonInterface;
use Database\Factories\AssetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;
use LogicException;

#[Fillable([
    'company_id', 'nama', 'category_id', 'asset_model_id', 'no_seri', 'foto', 'room_id', 'default_room_id',
    'supplier_id', 'tgl_beli', 'harga_beli', 'depreciation_id', 'order_number', 'garansi_sampai', 'eol_date',
    'spesifikasi', 'is_byod', 'requestable', 'bookable', 'next_audit_date', 'notes',
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
        'bookable' => false,
    ];

    // Batas hari pengingat bawaan di dashboard dan ringkasan email.
    public const int WARRANTY_WARNING_DAYS = 60;

    public const int EOL_WARNING_DAYS = 90;

    public const int AUDIT_WARNING_DAYS = 14;

    // Status hanya boleh berubah lewat method di model ini, bukan lewat form atau update biasa.
    private bool $statusChangeAllowed = false;

    // Perusahaan juga, lewat transferTo(), supaya pemegang, ruang, dan barang yang menempel ikut dicek.
    private bool $companyChangeAllowed = false;

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
            // Hanya saat model atau data pembelian berubah, supaya ganti status massal tidak memuat model aset satu per satu.
            if ($asset->exists && ! $asset->isDirty(['asset_model_id', 'tgl_beli', 'depreciation_id', 'eol_date'])) {
                return;
            }

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

            if ($asset->isDirty('company_id') && ! $asset->companyChangeAllowed) {
                throw new LogicException(__('Perusahaan aset hanya berubah lewat Pindah perusahaan.'));
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
     * Hanya Disiapkan <-> Tersedia; status lain berubah lewat transaksi.
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
     * Aset Hilang ditemukan kembali: Tersedia di ruang yang dipilih, pemegang lamanya dilepas, alasannya dicatat.
     */
    public function recover(Room $room, string $reason): void
    {
        if (blank($reason)) {
            throw new LogicException(__('Keterangan wajib diisi.'));
        }

        DB::transaction(function () use ($room, $reason): void {
            $asset = static::query()->with('status')->whereKey($this->getKey())->lockForUpdate()->firstOrFail();

            if ($asset->status->nama !== Status::HILANG) {
                throw new LogicException(__('Hanya aset Hilang yang bisa ditandai ditemukan kembali.'));
            }

            $asset->ensureNoPendingDisposal();

            if (Assignment::companyIdOf($room) !== $asset->company_id) {
                throw new LogicException(__('Ruang :ruang berada di perusahaan lain.', ['ruang' => $room->nama]));
            }

            $asset->transitionTo(Status::named(Status::TERSEDIA), ['assigned_type' => null, 'assigned_id' => null, 'room_id' => $room->getKey()]);

            activity()
                ->performedOn($asset)
                ->event('ditemukan_kembali')
                ->withProperties(['alasan' => $reason])
                ->log(__('Aset ditemukan kembali'));
        });

        $this->refresh();
    }

    /**
     * Kode tetap; hanya aset yang tidak dipakai dan tidak membawa barang lain, karena barang itu milik perusahaan asal.
     */
    public function transferTo(Company $company, Room $room, string $tanggal, string $alasan): void
    {
        DB::transaction(function () use ($company, $room, $tanggal, $alasan): void {
            $asset = static::query()->with(['status', 'company'])->whereKey($this->getKey())->lockForUpdate()->firstOrFail();

            if (! in_array($asset->status->nama, [Status::DISIAPKAN, Status::TERSEDIA], true)) {
                throw new LogicException(__('Hanya aset Disiapkan atau Tersedia yang bisa dipindah ke perusahaan lain. Kembalikan dulu kalau sedang dipakai.'));
            }

            if ($company->is($asset->company)) {
                throw new LogicException(__('Aset :kode sudah milik :perusahaan.', ['kode' => $asset->kode, 'perusahaan' => $company->nama]));
            }

            if (! $company->is_active) {
                throw new LogicException(__('Perusahaan :perusahaan sudah nonaktif.', ['perusahaan' => $company->nama]));
            }

            if (Assignment::companyIdOf($room) !== $company->getKey()) {
                throw new LogicException(__('Ruang :ruang berada di perusahaan lain.', ['ruang' => $room->nama]));
            }

            $attached = $asset->attachedItemLabels();

            if ($attached->isNotEmpty()) {
                throw new LogicException(__('Aset :kode masih membawa :list. Lepas dulu sebelum dipindah.', ['kode' => $asset->kode, 'list' => $attached->implode(', ')]));
            }

            AssetTransfer::query()->create([
                'asset_id' => $asset->getKey(),
                'from_company_id' => $asset->company_id,
                'to_company_id' => $company->getKey(),
                'from_room_id' => $asset->room_id,
                'to_room_id' => $room->getKey(),
                'tanggal' => $tanggal,
                'alasan' => $alasan,
                'created_by' => auth()->id(),
            ]);

            $asset->companyChangeAllowed = true;

            try {
                $asset->update(['company_id' => $company->getKey(), 'room_id' => $room->getKey(), 'default_room_id' => $room->getKey()]);
            } finally {
                $asset->companyChangeAllowed = false;
            }
        });

        $this->refresh();
    }

    /**
     * Barang yang menempel di aset ini, dari pengelola mana pun: aset lain, seat lisensi, dan komponen terpasang.
     *
     * @return SupportCollection<int, string>
     */
    public function attachedItemLabels(): SupportCollection
    {
        $attachedAssets = static::withoutGlobalScopes()
            ->where('assigned_type', $this->getMorphClass())
            ->where('assigned_id', $this->getKey())
            ->whereRelation('status', 'nama', Status::DIPAKAI)
            ->get();
        $seats = LicenseSeat::withoutGlobalScopes()->where('asset_id', $this->getKey())->whereNull('dilepas_pada')
            ->with(['license' => fn (BelongsTo $query) => $query->withoutGlobalScopes()])
            ->get();
        $components = ComponentInstallation::withoutGlobalScopes()->open()->where('asset_id', $this->getKey())
            ->with(['component' => fn (BelongsTo $query) => $query->withoutGlobalScopes()])
            ->get();

        return $attachedAssets->toBase()->map(fn (Asset $asset): string => Assignment::labelFor($asset))
            ->merge($seats->toBase()->map(fn (LicenseSeat $seat): string => $seat->label()))
            ->merge($components->toBase()->map(fn (ComponentInstallation $installation): string => "{$installation->jumlah} × {$installation->component->nama}"));
    }

    // Barang berperusahaan (seat lisensi, komponen) hanya dipasang ke aset yang belum dihapuskan di perusahaan yang sama.
    public function ensureCanReceive(Model $item): void
    {
        if ($this->status->nama === Status::DIHAPUSKAN) {
            throw new LogicException(__('Aset :kode sudah dihapuskan.', ['kode' => $this->kode]));
        }

        if ((int) $this->company_id !== (int) $item->getAttribute('company_id')) {
            throw new LogicException(__('Aset :kode berada di perusahaan lain.', ['kode' => $this->kode]));
        }
    }

    // Ajuan penghapusan yang masih menunggu harus ditolak dulu sebelum aset dipakai lagi.
    public function ensureNoPendingDisposal(): void
    {
        if (Disposal::withoutGlobalScopes()->where('asset_id', $this->getKey())->where('status', DisposalStatus::Diajukan)->exists()) {
            throw new LogicException(__('Aset :kode punya ajuan penghapusan yang menunggu keputusan. Minta Super Admin menolaknya dulu.', ['kode' => $this->kode]));
        }
    }

    /**
     * Dipegang karyawan atau departemen, langsung atau lewat aset induknya, jadi tidak terikat ke satu ruang.
     */
    public function isWithEmployeeOrDepartment(): bool
    {
        $parentIds = $this->parentIds();
        $holder = $parentIds === [] ? $this : static::withoutGlobalScopes()->find(end($parentIds), ['id', 'assigned_type']);

        return in_array($holder?->assigned_type, [(new Employee)->getMorphClass(), (new Department)->getMorphClass()], true);
    }

    /**
     * Garis lurus per bulan sejak tanggal beli, minimal 0; null untuk BYOD atau data pembelian yang belum lengkap.
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

    #[Scope]
    protected function warrantyEndingSoon(Builder $query, ?CarbonInterface $until = null): void
    {
        $query->whereDate('garansi_sampai', '>=', today())
            ->whereDate('garansi_sampai', '<=', $until ?? today()->addDays(static::WARRANTY_WARNING_DAYS))
            ->whereRelation('status', 'nama', '!=', Status::DIHAPUSKAN);
    }

    #[Scope]
    protected function eolSoon(Builder $query, ?CarbonInterface $until = null): void
    {
        $query->whereDate('eol_date', '>=', today())
            ->whereDate('eol_date', '<=', $until ?? today()->addDays(static::EOL_WARNING_DAYS))
            ->whereRelation('status', 'nama', '!=', Status::DIHAPUSKAN);
    }

    // Aset Hilang dan Dihapuskan tidak diaudit.
    #[Scope]
    protected function auditable(Builder $query): void
    {
        $query->whereDoesntHave('status', fn (Builder $query) => $query->whereIn('nama', [Status::HILANG, Status::DIHAPUSKAN]));
    }

    // Termasuk audit yang sudah terlambat.
    #[Scope]
    protected function auditDueSoon(Builder $query, ?CarbonInterface $until = null): void
    {
        $query->whereDate('next_audit_date', '<=', $until ?? today()->addDays(static::AUDIT_WARNING_DAYS))->auditable();
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
     * @return HasMany<ComponentInstallation, $this>
     */
    public function componentInstallations(): HasMany
    {
        return $this->hasMany(ComponentInstallation::class);
    }

    /**
     * @return HasMany<AuditItem, $this>
     */
    public function auditItems(): HasMany
    {
        return $this->hasMany(AuditItem::class);
    }

    /**
     * @return HasMany<AssetTransfer, $this>
     */
    public function transfers(): HasMany
    {
        return $this->hasMany(AssetTransfer::class);
    }

    /**
     * @return HasMany<AssetReminder, $this>
     */
    public function reminders(): HasMany
    {
        return $this->hasMany(AssetReminder::class);
    }

    /**
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
            'bookable' => 'boolean',
            'next_audit_date' => 'date',
        ];
    }
}
