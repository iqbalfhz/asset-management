<?php

namespace App\Models;

use App\Models\Concerns\LogsChanges;
use Database\Factories\LicenseSeatFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use LogicException;

#[Fillable(['license_id', 'employee_id', 'asset_id', 'dipasang_pada', 'dipasang_oleh', 'dilepas_pada', 'dilepas_oleh'])]
class LicenseSeat extends Model
{
    /** @use HasFactory<LicenseSeatFactory> */
    use HasFactory, LogsChanges;

    protected static function booted(): void
    {
        // Seat ikut hak lihat lisensinya (perusahaan dan role pengelola).
        static::addGlobalScope('license', fn (Builder $query) => $query->whereHas('license'));
    }

    public function release(): void
    {
        DB::transaction(function (): void {
            $seat = static::withoutGlobalScopes()->whereKey($this->getKey())->lockForUpdate()->firstOrFail();

            if ($seat->dilepas_pada !== null) {
                throw new LogicException(__('Seat ini sudah dilepas.'));
            }

            $seat->update(['dilepas_pada' => now(), 'dilepas_oleh' => auth()->id()]);
        });

        $this->refresh();
    }

    /**
     * Lepas semua seat aktif milik karyawan atau aset, dari lisensi mana pun (offboarding paksa, aset dihapuskan).
     */
    public static function releaseAllFor(Employee|Asset $holder): void
    {
        static::withoutGlobalScopes()
            ->where($holder instanceof Employee ? 'employee_id' : 'asset_id', $holder->getKey())
            ->whereNull('dilepas_pada')
            ->lockForUpdate()
            ->get()
            ->each(fn (LicenseSeat $seat) => $seat->update(['dilepas_pada' => now(), 'dilepas_oleh' => auth()->id()]));
    }

    public static function labelFor(Employee|Asset $holder): string
    {
        return $holder instanceof Employee ? $holder->nama : "{$holder->kode} ({$holder->nama})";
    }

    public function holderLabel(): string
    {
        return static::labelFor($this->employee ?? $this->asset);
    }

    /**
     * @return BelongsTo<License, $this>
     */
    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }

    /**
     * Karyawan dibaca tanpa scope perusahaan: seat sudah lolos hak lihat lewat lisensinya.
     *
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class)->withoutGlobalScopes();
    }

    /**
     * Aset dibaca tanpa scope kategori: lisensi IT bisa terpasang di aset yang dikelola role lain.
     *
     * @return BelongsTo<Asset, $this>
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class)->withoutGlobalScopes();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function installer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dipasang_oleh');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'dipasang_pada' => 'datetime',
            'dilepas_pada' => 'datetime',
        ];
    }
}
