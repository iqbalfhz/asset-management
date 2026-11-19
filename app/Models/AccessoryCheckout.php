<?php

namespace App\Models;

use App\Models\Concerns\LogsChanges;
use App\Models\Concerns\ReleasesAllocation;
use Database\Factories\AccessoryCheckoutFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

#[Fillable(['accessory_id', 'employee_id', 'jumlah', 'diserahkan_pada', 'diserahkan_oleh', 'dikembalikan_pada', 'dikembalikan_oleh', 'catatan'])]
class AccessoryCheckout extends Model
{
    /** @use HasFactory<AccessoryCheckoutFactory> */
    use HasFactory, LogsChanges, ReleasesAllocation;

    public const string OPENED_AT = 'diserahkan_pada';

    public const string RELEASED_AT = 'dikembalikan_pada';

    public const string RELEASED_BY = 'dikembalikan_oleh';

    protected static function booted(): void
    {
        // Ikut hak lihat aksesorinya (perusahaan dan role pengelola).
        static::addGlobalScope('accessory', fn (Builder $query) => $query->whereHas('accessory'));
    }

    // Offboarding paksa: unit yang tidak kembali dicatat hilang, jadi jumlah aksesorinya ikut berkurang.
    public function writeOff(): void
    {
        DB::transaction(function (): void {
            $this->release();

            $accessory = Accessory::withoutGlobalScopes()->whereKey($this->accessory_id)->lockForUpdate()->firstOrFail();
            $accessory->update(['jumlah' => $accessory->jumlah - $this->jumlah]);
        });
    }

    /**
     * @return BelongsTo<Accessory, $this>
     */
    public function accessory(): BelongsTo
    {
        return $this->belongsTo(Accessory::class);
    }

    /**
     * Karyawan dibaca tanpa scope perusahaan: baris ini sudah lolos hak lihat lewat aksesorinya.
     *
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class)->withoutGlobalScopes();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function giver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diserahkan_oleh');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'jumlah' => 'integer',
            'diserahkan_pada' => 'datetime',
            'dikembalikan_pada' => 'datetime',
        ];
    }
}
