<?php

namespace App\Models;

use App\Enums\AuditResult;
use App\Enums\AuditStatus;
use App\Models\Concerns\LogsChanges;
use App\Models\Scopes\ManagedCategoryScope;
use Database\Factories\AuditFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use LogicException;

#[Fillable(['room_id', 'role_id', 'status', 'catatan', 'dibuat_oleh', 'ditutup_oleh', 'mulai_pada', 'selesai_pada'])]
class Audit extends Model
{
    /** @use HasFactory<AuditFactory> */
    use HasFactory, LogsChanges;

    public const int DEFAULT_INTERVAL_MONTHS = 12;

    protected static function booted(): void
    {
        // Sesi ikut perusahaan ruangnya dan role pengelolanya, sama seperti hak lihat aset.
        static::addGlobalScope('room', fn (Builder $query) => $query->whereHas('room'));
        static::addGlobalScope('role', function (Builder $query): void {
            $roleIds = ManagedCategoryScope::restrictedRoleIds();

            if ($roleIds !== null) {
                $query->whereIn($query->qualifyColumn('role_id'), $roleIds);
            }
        });
    }

    /**
     * Mulai sesi untuk satu ruang. Daftar aset yang diharapkan diambil sekarang, dengan hak lihat user yang membuat.
     */
    public static function start(Room $room, ?string $catatan = null): self
    {
        $user = auth()->user();
        $roleId = $user->roles->first()?->getKey() ?? throw new LogicException(__('Akun Anda belum punya role.'));

        return DB::transaction(function () use ($room, $catatan, $user, $roleId): self {
            $running = static::withoutGlobalScopes()
                ->where('room_id', $room->getKey())
                ->where('role_id', $roleId)
                ->where('status', AuditStatus::Berjalan)
                ->exists();

            if ($running) {
                throw new LogicException(__('Ruang :ruang sedang diaudit. Tutup sesi itu dulu.', ['ruang' => $room->nama]));
            }

            $audit = static::query()->create([
                'room_id' => $room->getKey(),
                'role_id' => $roleId,
                'status' => AuditStatus::Berjalan,
                'catatan' => $catatan,
                'dibuat_oleh' => $user->getKey(),
                'mulai_pada' => now(),
            ]);

            $expected = Asset::query()
                ->where('room_id', $room->getKey())
                ->whereHas('status', fn (Builder $query) => $query->whereNotIn('nama', [Status::HILANG, Status::DIHAPUSKAN]))
                ->get(['id', 'room_id']);

            $audit->items()->createMany($expected->map(fn (Asset $asset): array => [
                'asset_id' => $asset->getKey(),
                'hasil' => AuditResult::Menunggu,
                'ruang_tercatat_id' => $asset->room_id,
            ])->all());

            return $audit;
        });
    }

    /**
     * Catat satu scan. Isinya boleh kode aset (barcode, ketikan) atau alamat QR label (.../a/KODE).
     * Aset yang sudah discan dikembalikan apa adanya.
     */
    public function scan(string $scanned): AuditItem
    {
        $kode = static::codeFrom($scanned);

        // Dicari dengan hak lihat user, jadi aset di luar kelolaannya dianggap tidak dikenal.
        $asset = Asset::query()->with('status')->where('kode', $kode)->first()
            ?? throw new LogicException(__('Kode :kode tidak dikenal atau bukan aset yang Anda kelola.', ['kode' => $kode]));

        if ($asset->status->nama === Status::DIHAPUSKAN) {
            throw new LogicException(__('Aset :kode sudah dihapuskan.', ['kode' => $asset->kode]));
        }

        return DB::transaction(function () use ($asset): AuditItem {
            $this->ensureRunning();

            $item = AuditItem::withoutGlobalScopes()
                ->where('audit_id', $this->getKey())
                ->where('asset_id', $asset->getKey())
                ->lockForUpdate()
                ->first();

            if ($item !== null && $item->hasil !== AuditResult::Menunggu) {
                return $item;
            }

            $attributes = [
                'hasil' => (int) $asset->room_id === (int) $this->room_id ? AuditResult::Ditemukan : AuditResult::SalahTempat,
                'ruang_tercatat_id' => $asset->room_id,
                'discan_oleh' => auth()->id(),
                'discan_pada' => now(),
            ];

            if ($item !== null) {
                $item->update($attributes);

                return $item;
            }

            return $this->items()->create($attributes + ['asset_id' => $asset->getKey()]);
        });
    }

    /**
     * Tutup sesi: yang belum discan menjadi Tidak ditemukan, aset yang ditemukan mendapat jadwal audit berikutnya.
     */
    public function close(): void
    {
        DB::transaction(function (): void {
            $this->ensureRunning();

            AuditItem::withoutGlobalScopes()
                ->where('audit_id', $this->getKey())
                ->where('hasil', AuditResult::Menunggu)
                ->update(['hasil' => AuditResult::TidakDitemukan]);

            $this->update(['status' => AuditStatus::Selesai, 'selesai_pada' => now(), 'ditutup_oleh' => auth()->id()]);

            $found = Asset::withoutGlobalScopes()
                ->whereIn('id', AuditItem::withoutGlobalScopes()->where('audit_id', $this->getKey())->whereIn('hasil', AuditResult::found())->select('asset_id'))
                ->get(['id', 'company_id']);

            // Interval mengikuti pengaturan perusahaan masing-masing aset.
            foreach ($found->groupBy('company_id') as $companyId => $assets) {
                $months = (int) Setting::get('audit_interval_bulan', static::DEFAULT_INTERVAL_MONTHS, (int) $companyId);

                Asset::withoutGlobalScopes()
                    ->whereKey($assets->modelKeys())
                    ->update(['next_audit_date' => now()->addMonthsNoOverflow($months)->toDateString()]);
            }
        });
    }

    /**
     * Jumlah aset per hasil, untuk ringkasan sesi dan laporan.
     *
     * @return array<string, int>
     */
    public function resultCounts(): array
    {
        $counts = $this->items()->toBase()->selectRaw('hasil, count(*) as total')->groupBy('hasil')->pluck('total', 'hasil');

        return collect(AuditResult::cases())
            ->mapWithKeys(fn (AuditResult $result): array => [$result->value => (int) ($counts[$result->value] ?? 0)])
            ->all();
    }

    // Isi QR label berupa alamat .../a/KODE; barcode dan ketikan manual berisi kode saja.
    private static function codeFrom(string $scanned): string
    {
        $scanned = trim($scanned);

        if (preg_match('~/a/([A-Za-z0-9-]+)/?$~', $scanned, $matches) === 1) {
            return strtoupper($matches[1]);
        }

        return strtoupper($scanned);
    }

    // Dibaca ulang dengan kunci baris, supaya scan tidak masuk ke sesi yang sedang ditutup.
    private function ensureRunning(): void
    {
        $current = static::withoutGlobalScopes()->whereKey($this->getKey())->lockForUpdate()->value('status');

        if ($current !== AuditStatus::Berjalan) {
            throw new LogicException(__('Sesi audit ini sudah ditutup.'));
        }
    }

    /**
     * @return BelongsTo<Room, $this>
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ditutup_oleh');
    }

    /**
     * @return HasMany<AuditItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(AuditItem::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AuditStatus::class,
            'mulai_pada' => 'datetime',
            'selesai_pada' => 'datetime',
        ];
    }
}
