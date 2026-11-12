<?php

namespace App\Models;

use App\Models\Concerns\LogsChanges;
use App\Models\Scopes\CompanyScope;
use App\Models\Scopes\ManagedRoleScope;
use Database\Factories\LicenseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use LogicException;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable(['company_id', 'role_id', 'nama_software', 'product_key', 'jumlah_seat', 'tgl_habis', 'harga', 'supplier_id', 'catatan'])]
#[ScopedBy([CompanyScope::class, ManagedRoleScope::class])]
class License extends Model
{
    /** @use HasFactory<LicenseFactory> */
    use HasFactory, LogsChanges;

    public const int EXPIRY_WARNING_DAYS = 60;

    protected static function booted(): void
    {
        static::updating(function (License $license): void {
            if ($license->isDirty('jumlah_seat') && $license->jumlah_seat < $license->usedSeats()) {
                throw new LogicException(__('Jumlah seat tidak bisa di bawah :count seat yang sedang dipakai.', ['count' => $license->usedSeats()]));
            }
        });

        static::deleting(function (License $license): void {
            if ($license->seats()->withoutGlobalScopes()->exists()) {
                throw new LogicException(__('Lisensi yang pernah dipakai tidak bisa dihapus.'));
            }
        });
    }

    // Product key tidak ikut tercatat di log aktivitas, supaya tidak tersimpan sebagai teks biasa.
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logExcept(['product_key', 'created_at', 'updated_at'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /**
     * Pasang satu seat ke karyawan atau aset. Baris lisensi dikunci dulu, supaya seat terakhir tidak dipakai dua admin bersamaan.
     */
    public function assignSeat(Employee|Asset $holder): LicenseSeat
    {
        return DB::transaction(function () use ($holder): LicenseSeat {
            $license = static::withoutGlobalScopes()->whereKey($this->getKey())->lockForUpdate()->firstOrFail();
            $label = LicenseSeat::labelFor($holder);

            if ($license->isExpired()) {
                throw new LogicException(__('Lisensi :nama sudah lewat tanggal habis.', ['nama' => $license->nama_software]));
            }

            if ((int) $holder->company_id !== (int) $license->company_id) {
                throw new LogicException(__(':pemegang berada di perusahaan lain.', ['pemegang' => $label]));
            }

            if ($holder instanceof Employee && ! $holder->is_active) {
                throw new LogicException(__('Karyawan :nama sudah nonaktif.', ['nama' => $holder->nama]));
            }

            if ($holder instanceof Asset && $holder->status->nama === Status::DIHAPUSKAN) {
                throw new LogicException(__('Aset :kode sudah dihapuskan.', ['kode' => $holder->kode]));
            }

            $column = $holder instanceof Employee ? 'employee_id' : 'asset_id';
            $activeSeats = LicenseSeat::withoutGlobalScopes()->where('license_id', $license->getKey())->whereNull('dilepas_pada');

            if ((clone $activeSeats)->where($column, $holder->getKey())->exists()) {
                throw new LogicException(__(':pemegang sudah memakai seat lisensi ini.', ['pemegang' => $label]));
            }

            if ($activeSeats->count() >= $license->jumlah_seat) {
                throw new LogicException(__('Seat :nama sudah habis (:jumlah dari :jumlah terpakai).', ['nama' => $license->nama_software, 'jumlah' => $license->jumlah_seat]));
            }

            return LicenseSeat::query()->create([
                'license_id' => $license->getKey(),
                $column => $holder->getKey(),
                'dipasang_pada' => now(),
                'dipasang_oleh' => auth()->id(),
            ]);
        });
    }

    public function usedSeats(): int
    {
        return LicenseSeat::withoutGlobalScopes()->where('license_id', $this->getKey())->whereNull('dilepas_pada')->count();
    }

    public function isExpired(): bool
    {
        return $this->tgl_habis !== null && $this->tgl_habis->lt(today());
    }

    // Lisensi yang sudah lewat atau akan habis dalam masa peringatan.
    #[Scope]
    protected function expiringSoon(Builder $query): void
    {
        $query->whereNotNull('tgl_habis')->whereDate('tgl_habis', '<=', today()->addDays(static::EXPIRY_WARNING_DAYS));
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Role admin yang mengelola lisensi ini.
     *
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return HasMany<LicenseSeat, $this>
     */
    public function seats(): HasMany
    {
        return $this->hasMany(LicenseSeat::class);
    }

    /**
     * @return HasMany<LicenseSeat, $this>
     */
    public function activeSeats(): HasMany
    {
        return $this->seats()->whereNull('dilepas_pada');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'product_key' => 'encrypted',
            'jumlah_seat' => 'integer',
            'tgl_habis' => 'date',
            'harga' => 'integer',
        ];
    }
}
