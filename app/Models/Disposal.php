<?php

namespace App\Models;

use App\Enums\DisposalMethod;
use App\Enums\DisposalStatus;
use App\Models\Concerns\LogsChanges;
use Database\Factories\DisposalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use LogicException;

#[Fillable([
    'asset_id', 'alasan', 'metode', 'nilai_jual', 'diajukan_oleh', 'disetujui_oleh',
    'status', 'tanggal', 'catatan', 'no_berita_acara',
])]
class Disposal extends Model
{
    /** @use HasFactory<DisposalFactory> */
    use HasFactory, LogsChanges;

    protected static function booted(): void
    {
        // Ajuan ikut hak lihat asetnya (perusahaan dan kategori pengelola).
        static::addGlobalScope('asset', fn (Builder $query) => $query->whereHas('asset'));
    }

    /**
     * Admin pengelola mengajukan penghapusan aset Rusak atau Hilang.
     *
     * @param  array{alasan: string, metode: DisposalMethod|string, nilai_jual?: ?int}  $data
     */
    public static function submit(Asset $asset, array $data): self
    {
        return DB::transaction(function () use ($asset, $data): self {
            $asset = Asset::query()->with('status')->whereKey($asset->getKey())->lockForUpdate()->firstOrFail();

            if (! in_array($asset->status->nama, [Status::RUSAK, Status::HILANG], true)) {
                throw new LogicException(__('Hanya aset Rusak atau Hilang yang bisa diajukan penghapusan.'));
            }

            if (static::withoutGlobalScopes()->where('asset_id', $asset->getKey())->where('status', DisposalStatus::Diajukan)->exists()) {
                throw new LogicException(__('Aset :kode sudah punya ajuan penghapusan yang menunggu keputusan.', ['kode' => $asset->kode]));
            }

            $method = DisposalMethod::from($data['metode'] instanceof DisposalMethod ? $data['metode']->value : $data['metode']);

            return static::query()->create([
                'asset_id' => $asset->getKey(),
                'alasan' => $data['alasan'],
                'metode' => $method,
                'nilai_jual' => $method === DisposalMethod::Dijual ? ($data['nilai_jual'] ?? null) : null,
                'diajukan_oleh' => auth()->id(),
                'status' => DisposalStatus::Diajukan,
            ]);
        });
    }

    /**
     * Super Admin menyetujui: aset Dihapuskan, pemegang dikosongkan, nomor berita acara terbit.
     */
    public function approve(?string $catatan = null): void
    {
        DB::transaction(function () use ($catatan): void {
            $this->ensureDecidable();

            $asset = Asset::withoutGlobalScopes()->with('status')->whereKey($this->asset_id)->lockForUpdate()->firstOrFail();

            if (! in_array($asset->status->nama, [Status::RUSAK, Status::HILANG], true)) {
                throw new LogicException(__('Aset :kode sekarang berstatus :status, jadi tidak bisa dihapuskan.', ['kode' => $asset->kode, 'status' => $asset->status->nama]));
            }

            $this->update([
                'status' => DisposalStatus::Disetujui,
                'disetujui_oleh' => auth()->id(),
                'tanggal' => now()->toDateString(),
                'catatan' => $catatan,
                'no_berita_acara' => static::nextNumber($asset->company_id),
            ]);

            $asset->transitionTo(Status::named(Status::DIHAPUSKAN), ['assigned_type' => null, 'assigned_id' => null]);
        });
    }

    public function reject(string $catatan): void
    {
        DB::transaction(function () use ($catatan): void {
            $this->ensureDecidable();

            $this->update([
                'status' => DisposalStatus::Ditolak,
                'disetujui_oleh' => auth()->id(),
                'tanggal' => now()->toDateString(),
                'catatan' => $catatan,
            ]);
        });
    }

    /**
     * Nomor BAP/{KODE}/{TAHUN}/{NOMOR}, berurutan per perusahaan dan tahun.
     */
    public static function nextNumber(int $companyId): string
    {
        return DB::transaction(function () use ($companyId): string {
            $key = ['company_id' => $companyId, 'tahun' => now()->year];

            DB::table('disposal_sequences')->insertOrIgnore($key + ['nomor_terakhir' => 0]);

            $sequence = DB::table('disposal_sequences')->where($key)->lockForUpdate()->first();
            $number = $sequence->nomor_terakhir + 1;

            DB::table('disposal_sequences')->where('id', $sequence->id)->update(['nomor_terakhir' => $number]);

            return sprintf('BAP/%s/%d/%04d', Company::query()->whereKey($companyId)->value('kode'), $key['tahun'], $number);
        });
    }

    // Hanya Super Admin yang memutuskan, dan hanya ajuan yang masih menunggu.
    private function ensureDecidable(): void
    {
        if (! (auth()->user()?->hasRole('super_admin') ?? false)) {
            throw new LogicException(__('Hanya Super Admin yang bisa menyetujui atau menolak penghapusan.'));
        }

        /** @var DisposalStatus $current */
        $current = static::withoutGlobalScopes()->whereKey($this->getKey())->lockForUpdate()->value('status');

        if ($current !== DisposalStatus::Diajukan) {
            throw new LogicException(__('Ajuan ini sudah :status.', ['status' => strtolower($current->getLabel())]));
        }
    }

    /**
     * @return BelongsTo<Asset, $this>
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diajukan_oleh');
    }

    /**
     * Super Admin yang menyetujui atau menolak.
     *
     * @return BelongsTo<User, $this>
     */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disetujui_oleh');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metode' => DisposalMethod::class,
            'status' => DisposalStatus::class,
            'nilai_jual' => 'integer',
            'tanggal' => 'date',
        ];
    }
}
