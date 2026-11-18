<?php

namespace App\Models;

use App\Models\Concerns\LogsChanges;
use App\Models\Concerns\ReceivesAssets;
use App\Models\Scopes\CompanyScope;
use App\Models\Scopes\ManagedCategoryScope;
use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;
use LogicException;

#[Fillable(['company_id', 'nik', 'nama', 'jabatan', 'department_id', 'user_id', 'is_active'])]
#[ScopedBy([CompanyScope::class])]
class Employee extends Model
{
    /** @use HasFactory<EmployeeFactory> */
    use HasFactory, LogsChanges, ReceivesAssets;

    protected $attributes = [
        'is_active' => true,
    ];

    // Karyawan nonaktif tidak menerima apa pun lagi: aset, barang habis pakai, aksesori, atau seat lisensi.
    public function ensureActive(): void
    {
        if (! $this->is_active) {
            throw new LogicException(__('Karyawan :nama sudah nonaktif.', ['nama' => $this->nama]));
        }
    }

    // Barang berperusahaan hanya diterima karyawan aktif di perusahaan yang sama.
    public function ensureCanReceive(Model $item): void
    {
        $this->ensureActive();

        if ((int) $this->company_id !== (int) $item->getAttribute('company_id')) {
            throw new LogicException(__(':nama berada di perusahaan lain.', ['nama' => $this->nama]));
        }
    }

    /**
     * Nonaktifkan karyawan yang resign. Ditolak selama masih memegang barang, kecuali dipaksa dengan alasan.
     */
    public function deactivate(?string $forcedReason = null): void
    {
        DB::transaction(function () use ($forcedReason): void {
            $held = $this->heldAssets()->lockForUpdate()->get();
            $seats = $this->activeLicenseSeats()->lockForUpdate()->get();
            $accessories = $this->activeAccessoryCheckouts()->lockForUpdate()->get();

            if ($held->isNotEmpty() || $seats->isNotEmpty() || $accessories->isNotEmpty()) {
                if (blank($forcedReason)) {
                    throw new LogicException(__(':nama masih memegang :barang. Proses pengembaliannya dulu.', [
                        'nama' => $this->nama,
                        'barang' => static::describeHoldings($held->count(), $seats->count(), (int) $accessories->sum('jumlah')),
                    ]));
                }

                foreach ($held as $asset) {
                    $asset->transitionTo(Status::named(Status::HILANG));
                }

                LicenseSeat::releaseAllFor($this);
                $accessories->each(fn (AccessoryCheckout $checkout) => $checkout->writeOff());

                activity()
                    ->performedOn($this)
                    ->event('dinonaktifkan_paksa')
                    ->withProperties([
                        'alasan' => $forcedReason,
                        'aset' => $held->pluck('kode')->all(),
                        'seat_lisensi' => $seats->map(fn (LicenseSeat $seat): string => $seat->license->nama_software)->all(),
                        'aksesori' => $accessories->map(fn (AccessoryCheckout $checkout): string => static::accessoryLabel($checkout))->all(),
                    ])
                    ->log(__('Karyawan dinonaktifkan paksa'));
            }

            $this->update(['is_active' => false]);
            $this->user?->update(['is_active' => false]);
        });
    }

    /**
     * Barang yang masih dipegang, untuk ditampilkan sebelum menonaktifkan.
     *
     * @return SupportCollection<int, string>
     */
    public function heldItemLabels(): SupportCollection
    {
        return $this->heldAssets()->get()->toBase()->map(fn (Asset $asset): string => "{$asset->kode} ({$asset->nama})")
            ->merge($this->activeLicenseSeats()->get()->toBase()->map(fn (LicenseSeat $seat): string => __('seat :software', ['software' => $seat->license->nama_software])))
            ->merge($this->activeAccessoryCheckouts()->get()->toBase()->map(fn (AccessoryCheckout $checkout): string => static::accessoryLabel($checkout)));
    }

    private static function accessoryLabel(AccessoryCheckout $checkout): string
    {
        return "{$checkout->jumlah} × {$checkout->accessory->nama}";
    }

    /**
     * Aset yang masih dipegang, dari kategori mana pun, supaya offboarding tidak melewatkan barang.
     *
     * @return MorphMany<Asset, $this>
     */
    public function heldAssets(): MorphMany
    {
        return $this->morphMany(Asset::class, 'assigned')
            ->withoutGlobalScope(ManagedCategoryScope::class)
            ->whereRelation('status', 'nama', Status::DIPAKAI);
    }

    /**
     * Seat lisensi yang belum dilepas, dari role pengelola mana pun, supaya offboarding tidak melewatkannya.
     *
     * @return HasMany<LicenseSeat, $this>
     */
    public function activeLicenseSeats(): HasMany
    {
        return $this->hasMany(LicenseSeat::class)
            ->withoutGlobalScope('license')
            ->with(['license' => fn ($query) => $query->withoutGlobalScopes()])
            ->whereNull('dilepas_pada');
    }

    /**
     * Aksesori yang masih dipegang, dari role pengelola mana pun, supaya offboarding tidak melewatkannya.
     *
     * @return HasMany<AccessoryCheckout, $this>
     */
    public function activeAccessoryCheckouts(): HasMany
    {
        return $this->hasMany(AccessoryCheckout::class)
            ->withoutGlobalScope('accessory')
            ->with(['accessory' => fn ($query) => $query->withoutGlobalScopes()])
            ->open();
    }

    /**
     * @return HasMany<AccessoryCheckout, $this>
     */
    public function accessoryCheckouts(): HasMany
    {
        return $this->hasMany(AccessoryCheckout::class);
    }

    /**
     * Aset yang tercatat dipegang karyawan ini, sebatas kategori yang dikelola admin yang membuka.
     *
     * @return MorphMany<Asset, $this>
     */
    public function assignedAssets(): MorphMany
    {
        return $this->morphMany(Asset::class, 'assigned');
    }

    /**
     * @return HasMany<LicenseSeat, $this>
     */
    public function licenseSeats(): HasMany
    {
        return $this->hasMany(LicenseSeat::class);
    }

    /**
     * Barang habis pakai yang diterima karyawan ini.
     *
     * @return HasMany<ConsumableTransaction, $this>
     */
    public function consumableTransactions(): HasMany
    {
        return $this->hasMany(ConsumableTransaction::class);
    }

    // Contoh: "2 aset dan 1 seat lisensi".
    public static function describeHoldings(int $assets, int $seats, int $accessories = 0): string
    {
        return collect([
            $assets > 0 ? __(':count aset', ['count' => $assets]) : null,
            $seats > 0 ? __(':count seat lisensi', ['count' => $seats]) : null,
            $accessories > 0 ? __(':count aksesori', ['count' => $accessories]) : null,
        ])->filter()->implode(' '.__('dan').' ');
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Akun login karyawan; boleh kosong karena tidak semua karyawan punya akun.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
