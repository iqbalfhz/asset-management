<?php

namespace App\Models;

use App\Enums\AssignmentStatus;
use App\Enums\AssignmentType;
use App\Enums\ReceiptMethod;
use Carbon\CarbonInterface;
use Closure;
use Database\Factories\AssignmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use LogicException;

#[Fillable([
    'asset_id', 'target_type', 'target_id', 'jenis', 'tanggal', 'kondisi', 'catatan', 'no_bast',
    'expected_return_at', 'status', 'accepted_at', 'signature_path', 'receipt_method', 'receipt_path', 'confirmed_by', 'created_by',
])]
class Assignment extends Model
{
    /** @use HasFactory<AssignmentFactory> */
    use HasFactory;

    // Batas hari pengingat bawaan di ringkasan email.
    public const int RETURN_WARNING_DAYS = 3;

    public const int UNACCEPTED_REMINDER_DAYS = 2;

    // Tanda tangan dan foto tanda terima di disk privat; hanya tampil di BAST yang diunduh lewat route berizin.
    public const string PROOF_DISK = 'local';

    // Nama jenis tujuan serah-terima di API.
    public const array TARGET_TYPES = [
        'karyawan' => Employee::class,
        'ruang' => Room::class,
        'departemen' => Department::class,
        'aset' => Asset::class,
    ];

    protected static function booted(): void
    {
        // Serah-terima ikut hak lihat asetnya (perusahaan dan kategori pengelola).
        static::addGlobalScope('asset', fn (Builder $query) => $query->whereHas('asset'));
    }

    /**
     * Satu BAST; karyawan yang tanda tangan di layar admin langsung tercatat menerima, tanpa itu menunggu konfirmasi.
     *
     * @param  Collection<int, Asset>  $assets
     * @param  array{tanggal: string, kondisi: string, catatan?: ?string, expected_return_at?: ?string, tanda_tangan?: ?string}  $details  tanda_tangan berisi PNG
     */
    public static function handOver(Collection $assets, Model $target, array $details): string
    {
        return DB::transaction(function () use ($assets, $target, $details): string {
            $assets = static::lockAssets($assets);

            foreach ($assets as $asset) {
                if ($asset->status->nama !== Status::TERSEDIA) {
                    throw new LogicException(__('Aset :kode belum Tersedia, jadi belum bisa diserahkan.', ['kode' => $asset->kode]));
                }
            }

            // Departemen dipakai bersama semua perusahaan, jadi boleh menerima aset perusahaan mana pun.
            if (! $target instanceof Department && static::companyIdOf($target) !== $assets->first()->company_id) {
                throw new LogicException(__('Tujuan serah-terima berada di perusahaan lain.'));
            }

            if ($target instanceof Employee) {
                $target->ensureActive();
            }

            if ($target instanceof Asset && array_intersect([$target->getKey(), ...$target->parentIds()], $assets->modelKeys()) !== []) {
                throw new LogicException(__('Aset tidak bisa diserahkan ke dirinya sendiri atau ke aset yang menempel padanya.'));
            }

            $noBast = static::nextBastNumber($assets->first()->company_id);
            $signedOnScreen = $target instanceof Employee && filled($details['tanda_tangan'] ?? null);
            $waitsForAcceptance = $target instanceof Employee && ! $signedOnScreen;
            $signaturePath = $signedOnScreen ? static::storeSignature($details['tanda_tangan']) : null;
            $roomId = match (true) {
                $target instanceof Room => $target->getKey(),
                $target instanceof Asset => $target->room_id,
                default => null,
            };

            foreach ($assets as $asset) {
                $asset->transitionTo(Status::named(Status::DIPAKAI), array_filter([
                    'assigned_type' => $target->getMorphClass(),
                    'assigned_id' => $target->getKey(),
                    'room_id' => $roomId,
                ]));

                static::query()->create([
                    'asset_id' => $asset->getKey(),
                    'target_type' => $target->getMorphClass(),
                    'target_id' => $target->getKey(),
                    'jenis' => AssignmentType::Keluar,
                    'tanggal' => $details['tanggal'],
                    'kondisi' => $details['kondisi'],
                    'catatan' => $details['catatan'] ?? null,
                    'no_bast' => $noBast,
                    'expected_return_at' => $details['expected_return_at'] ?? null,
                    'status' => $waitsForAcceptance ? AssignmentStatus::Menunggu : AssignmentStatus::Diterima,
                    'accepted_at' => $waitsForAcceptance ? null : now(),
                    'signature_path' => $signaturePath,
                    'receipt_method' => $signedOnScreen ? ReceiptMethod::Layar : null,
                    'confirmed_by' => $signedOnScreen ? auth()->id() : null,
                    'created_by' => auth()->id(),
                ]);
            }

            return $noBast;
        });
    }

    /**
     * Kembalikan aset yang sedang dipakai. Aset yang menempel ikut kembali dalam BAST yang sama.
     *
     * @param  Collection<int, Asset>  $assets
     * @param  array{tanggal: string, kondisi: string, catatan?: ?string}  $details
     */
    public static function takeBack(Collection $assets, array $details): string
    {
        return DB::transaction(function () use ($assets, $details): string {
            $assets = static::lockAssets($assets);

            foreach ($assets as $asset) {
                if ($asset->status->nama !== Status::DIPAKAI) {
                    throw new LogicException(__('Aset :kode tidak sedang dipakai.', ['kode' => $asset->kode]));
                }

                // Induk dibaca tanpa scope: induk dari kategori lain tetap menahan aset ini.
                $parent = $asset->assigned_type === $asset->getMorphClass()
                    ? Asset::withoutGlobalScopes()->with('status')->find($asset->assigned_id)
                    : null;

                if ($parent?->status->nama === Status::DIPAKAI && ! $assets->contains($parent)) {
                    throw new LogicException(__('Aset :kode menempel pada :induk yang masih dipakai; kembalikan induknya dulu.', ['kode' => $asset->kode, 'induk' => $parent->kode]));
                }
            }

            $returning = static::withAttachedAssets($assets);
            $noBast = static::nextBastNumber($returning->first()->company_id);

            foreach ($returning as $asset) {
                static::query()->create([
                    'asset_id' => $asset->getKey(),
                    'target_type' => $asset->assigned_type,
                    'target_id' => $asset->assigned_id,
                    'jenis' => AssignmentType::Kembali,
                    'tanggal' => $details['tanggal'],
                    'kondisi' => $details['kondisi'],
                    'catatan' => $details['catatan'] ?? null,
                    'no_bast' => $noBast,
                    'status' => AssignmentStatus::Diterima,
                    'accepted_at' => now(),
                    'created_by' => auth()->id(),
                ]);

                $asset->transitionTo(Status::named(Status::TERSEDIA), [
                    'assigned_type' => null,
                    'assigned_id' => null,
                    'room_id' => $asset->default_room_id ?? $asset->room_id,
                ]);
            }

            return $noBast;
        });
    }

    /**
     * Karyawan menerima serah-terima dalam satu BAST dengan tanda tangan di aplikasinya.
     */
    public static function accept(Employee $employee, string $noBast, string $signaturePng): int
    {
        return static::recordReceipt($employee, $noBast, fn (): array => [
            'receipt_method' => ReceiptMethod::Aplikasi,
            'signature_path' => static::storeSignature($signaturePng),
        ]);
    }

    /**
     * Admin mencatat penerimaan BAST ini atas nama karyawan: tanda tangan di layar admin, atau foto BAST cetak yang sudah ditandatangani.
     */
    public function confirmReceipt(?string $signaturePng, ?UploadedFile $receiptPhoto): void
    {
        $count = static::recordReceipt($this->target()->withoutGlobalScopes()->first(), $this->no_bast, fn (): array => $signaturePng !== null
            ? ['receipt_method' => ReceiptMethod::Layar, 'signature_path' => static::storeSignature($signaturePng)]
            : ['receipt_method' => ReceiptMethod::Kertas, 'receipt_path' => $receiptPhoto->store('receipts', static::PROOF_DISK)]);

        if ($count === 0) {
            throw new LogicException(__('BAST :nomor sudah diterima, atau asetnya sudah tidak dipegang karyawan.', ['nomor' => $this->no_bast]));
        }
    }

    /**
     * Bukti baru disimpan kalau ada baris yang masih dipegang karyawan; permintaan aset terkait ikut selesai.
     *
     * @param  Closure(): array<string, mixed>  $proof
     */
    private static function recordReceipt(Employee $employee, string $noBast, Closure $proof): int
    {
        return DB::transaction(function () use ($employee, $noBast, $proof): int {
            $ids = static::query()->waitingFor($employee)->where('no_bast', $noBast)->pluck('id')->all();

            if ($ids === []) {
                return 0;
            }

            static::withoutGlobalScope('asset')->whereKey($ids)->update($proof() + [
                'status' => AssignmentStatus::Diterima,
                'accepted_at' => now(),
                'confirmed_by' => auth()->id(),
            ]);
            AssetRequest::completeFor($ids);

            return count($ids);
        });
    }

    private static function storeSignature(string $png): string
    {
        $path = 'signatures/'.Str::uuid().'.png';
        Storage::disk(static::PROOF_DISK)->put($path, $png);

        return $path;
    }

    // Serah-terima ke karyawan yang belum diterima, untuk aset yang masih dipegangnya; tanpa batas kategori admin.
    #[Scope]
    protected function waitingFor(Builder $query, ?Employee $employee): void
    {
        $query->withoutGlobalScope('asset')
            ->where('jenis', AssignmentType::Keluar)
            ->where('status', AssignmentStatus::Menunggu)
            ->where('target_type', (new Employee)->getMorphClass())
            ->where('target_id', $employee?->getKey() ?? 0)
            ->stillWithTarget();
    }

    // Pinjaman yang tanggal kembalinya sudah lewat atau sampai batas (bawaan: RETURN_WARNING_DAYS hari lagi).
    #[Scope]
    protected function returnDueSoon(Builder $query, ?CarbonInterface $until = null): void
    {
        $query->where('jenis', AssignmentType::Keluar)
            ->whereDate('expected_return_at', '<=', $until ?? today()->addDays(static::RETURN_WARNING_DAYS))
            ->stillWithTarget();
    }

    #[Scope]
    protected function unacceptedTooLong(Builder $query): void
    {
        $query->where('jenis', AssignmentType::Keluar)
            ->where('status', AssignmentStatus::Menunggu)
            ->where('created_at', '<=', now()->subDays(static::UNACCEPTED_REMINDER_DAYS))
            ->stillWithTarget();
    }

    // Serah-terima terakhir aset itu, dan asetnya masih dipegang tujuannya (belum dikembalikan atau dipindah).
    #[Scope]
    protected function stillWithTarget(Builder $query): void
    {
        $query->whereNotExists(fn (QueryBuilder $later) => $later
            ->from('assignments as later')
            ->whereColumn('later.asset_id', 'assignments.asset_id')
            ->whereColumn('later.id', '>', 'assignments.id'))
            ->whereHas('asset', fn (Builder $asset) => $asset->withoutGlobalScopes()
                ->whereColumn('assets.assigned_type', 'assignments.target_type')
                ->whereColumn('assets.assigned_id', 'assignments.target_id'));
    }

    /**
     * Nomor BAST dari format di Pengaturan, berurutan per perusahaan dan tahun.
     */
    public static function nextBastNumber(int $companyId): string
    {
        return DB::transaction(function () use ($companyId): string {
            $key = ['company_id' => $companyId, 'tahun' => now()->year];

            DB::table('bast_sequences')->insertOrIgnore($key + ['nomor_terakhir' => 0]);

            $sequence = DB::table('bast_sequences')->where($key)->lockForUpdate()->first();
            $number = $sequence->nomor_terakhir + 1;

            DB::table('bast_sequences')->where('id', $sequence->id)->update(['nomor_terakhir' => $number]);

            return strtr(Setting::get('format_nomor_bast', 'BAST/{KODE}/{TAHUN}/{NOMOR}', $companyId), [
                '{KODE}' => Company::query()->whereKey($companyId)->value('kode'),
                '{TAHUN}' => (string) $key['tahun'],
                '{BULAN}' => now()->format('m'),
                '{NOMOR}' => sprintf('%04d', $number),
            ]);
        });
    }

    /**
     * Ambil ulang aset dengan kunci baris; aset di luar hak lihat user tidak ikut terbaca.
     *
     * @param  Collection<int, Asset>  $assets
     * @return Collection<int, Asset>
     */
    private static function lockAssets(Collection $assets): Collection
    {
        $locked = Asset::query()->with('status')->whereKey($assets->modelKeys())->lockForUpdate()->get();

        if ($locked->isEmpty() || $locked->count() !== $assets->count()) {
            throw new LogicException(__('Sebagian aset tidak ditemukan.'));
        }

        if ($locked->pluck('company_id')->unique()->count() > 1) {
            throw new LogicException(__('Satu BAST hanya untuk aset dari perusahaan yang sama.'));
        }

        return $locked;
    }

    /**
     * Tambahkan aset yang menempel (dan yang menempel padanya) dan masih dipakai.
     *
     * @param  Collection<int, Asset>  $assets
     * @return Collection<int, Asset>
     */
    private static function withAttachedAssets(Collection $assets): Collection
    {
        $all = $assets;
        $parents = $assets;

        while ($parents->isNotEmpty()) {
            $parents = Asset::withoutGlobalScopes()
                ->with('status')
                ->where('assigned_type', (new Asset)->getMorphClass())
                ->whereIn('assigned_id', $parents->modelKeys())
                ->whereRelation('status', 'nama', Status::DIPAKAI)
                ->whereKeyNot($all->modelKeys())
                ->lockForUpdate()
                ->get();

            $all = $all->merge($parents);
        }

        return $all;
    }

    /**
     * Tujuan serah-terima yang boleh dipilih sesuai jenisnya; karyawan nonaktif tidak termasuk.
     *
     * @return Builder<Model>|null
     */
    public static function targetQuery(?string $type): ?Builder
    {
        return match ($type) {
            Employee::class => Employee::query()->where('is_active', true),
            Room::class => Room::query()->with('floor.building'),
            Department::class => Department::query(),
            Asset::class => Asset::query(),
            default => null,
        };
    }

    public static function companyIdOf(Model $target): ?int
    {
        return $target instanceof Room
            ? Building::withoutGlobalScopes()->whereKey(Floor::withoutGlobalScopes()->whereKey($target->floor_id)->value('building_id'))->value('company_id')
            : $target->company_id;
    }

    public function targetLabel(): string
    {
        // Tanpa scope, supaya induk dari kategori lain tetap tertulis di BAST.
        $target = $this->target()->withoutGlobalScopes()->first();

        return $target ? static::labelFor($target) : '—';
    }

    /**
     * Tanda tangan penerima sebagai data URI untuk dokumen PDF.
     */
    public function signatureDataUri(): ?string
    {
        return static::proofDataUri($this->signature_path);
    }

    /**
     * Foto BAST cetak yang ditandatangani penerima, sebagai data URI untuk lampiran BAST.
     */
    public function receiptDataUri(): ?string
    {
        return static::proofDataUri($this->receipt_path);
    }

    private static function proofDataUri(?string $path): ?string
    {
        $disk = Storage::disk(static::PROOF_DISK);

        if ($path === null || ! $disk->exists($path)) {
            return null;
        }

        return 'data:'.$disk->mimeType($path).';base64,'.base64_encode($disk->get($path));
    }

    public static function labelFor(Model $target): string
    {
        return match (true) {
            $target instanceof Employee => "{$target->nama} ({$target->nik})",
            $target instanceof Room => $target->loadMissing('floor.building')->path(),
            $target instanceof Asset => "{$target->kode} — {$target->nama}",
            $target instanceof Department => $target->nama,
            default => '—',
        };
    }

    /**
     * @return BelongsTo<Asset, $this>
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function target(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * User yang mencatat penerimaan: karyawan sendiri lewat aplikasi, atau admin.
     *
     * @return BelongsTo<User, $this>
     */
    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'jenis' => AssignmentType::class,
            'status' => AssignmentStatus::class,
            'tanggal' => 'date',
            'expected_return_at' => 'date',
            'accepted_at' => 'datetime',
            'receipt_method' => ReceiptMethod::class,
        ];
    }
}
