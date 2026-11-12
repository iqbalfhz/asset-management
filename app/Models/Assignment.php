<?php

namespace App\Models;

use App\Enums\AssignmentStatus;
use App\Enums\AssignmentType;
use Database\Factories\AssignmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\DB;
use LogicException;

#[Fillable([
    'asset_id', 'target_type', 'target_id', 'jenis', 'tanggal', 'kondisi', 'catatan', 'no_bast',
    'expected_return_at', 'status', 'accepted_at', 'created_by',
])]
class Assignment extends Model
{
    /** @use HasFactory<AssignmentFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        // Serah-terima ikut hak lihat asetnya (perusahaan dan kategori pengelola).
        static::addGlobalScope('asset', fn (Builder $query) => $query->whereHas('asset'));
    }

    /**
     * Serahkan aset ke karyawan, ruang, departemen, atau aset lain dalam satu BAST.
     *
     * @param  Collection<int, Asset>  $assets
     * @param  array{tanggal: string, kondisi: string, catatan?: ?string, expected_return_at?: ?string}  $details
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

            if (static::companyIdOf($target) !== $assets->first()->company_id) {
                throw new LogicException(__('Tujuan serah-terima berada di perusahaan lain.'));
            }

            if ($target instanceof Employee && ! $target->is_active) {
                throw new LogicException(__('Karyawan :nama sudah nonaktif.', ['nama' => $target->nama]));
            }

            if ($target instanceof Asset && array_intersect([$target->getKey(), ...$target->parentIds()], $assets->modelKeys()) !== []) {
                throw new LogicException(__('Aset tidak bisa diserahkan ke dirinya sendiri atau ke aset yang menempel padanya.'));
            }

            $noBast = static::nextBastNumber($assets->first()->company_id);
            $waitsForAcceptance = $target instanceof Employee;
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
     * Karyawan menerima serah-terima dalam satu BAST; hanya aset yang masih dipegangnya.
     */
    public static function accept(Employee $employee, string $noBast): int
    {
        return static::withoutGlobalScope('asset')
            ->where('no_bast', $noBast)
            ->where('jenis', AssignmentType::Keluar)
            ->where('status', AssignmentStatus::Menunggu)
            ->where('target_type', $employee->getMorphClass())
            ->where('target_id', $employee->getKey())
            ->whereHas('asset', fn (Builder $query) => $query->withoutGlobalScopes()
                ->where('assigned_type', $employee->getMorphClass())
                ->where('assigned_id', $employee->getKey()))
            ->update(['status' => AssignmentStatus::Diterima, 'accepted_at' => now()]);
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

    public static function companyIdOf(Model $target): ?int
    {
        return $target instanceof Room
            ? Building::withoutGlobalScopes()->whereKey(Floor::withoutGlobalScopes()->whereKey($target->floor_id)->value('building_id'))->value('company_id')
            : $target->company_id;
    }

    /**
     * Nama tujuan untuk tabel dan BAST.
     */
    public function targetLabel(): string
    {
        // Tanpa scope, supaya induk dari kategori lain tetap tertulis di BAST.
        $target = $this->target()->withoutGlobalScopes()->first();

        return $target ? static::labelFor($target) : '—';
    }

    public static function labelFor(Model $target): string
    {
        return match (true) {
            $target instanceof Employee => "{$target->nama} ({$target->nik})",
            $target instanceof Room => $target->loadMissing('floor.building')->floor->building->nama.' — '.$target->floor->nama.' — '.$target->nama,
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
     * Get the attributes that should be cast.
     *
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
        ];
    }
}
