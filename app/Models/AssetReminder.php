<?php

namespace App\Models;

use App\Models\Concerns\LogsChanges;
use Carbon\CarbonInterface;
use Database\Factories\AssetReminderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use LogicException;

#[Fillable(['asset_id', 'jenis', 'tanggal', 'ulangi_bulan', 'catatan', 'selesai_pada'])]
class AssetReminder extends Model
{
    /** @use HasFactory<AssetReminderFactory> */
    use HasFactory, LogsChanges;

    // Masa tampil di dashboard dan ringkasan email.
    public const int WARNING_DAYS = 30;

    // Email ke admin pengelola dikirim sekian hari sebelum tanggalnya.
    public const array EMAIL_DAYS_BEFORE = [30, 7];

    protected static function booted(): void
    {
        // Pengingat ikut hak lihat asetnya (perusahaan dan kategori pengelola).
        static::addGlobalScope('asset', fn (Builder $query) => $query->whereHas('asset'));
    }

    /**
     * Selesai: yang berulang maju ke tanggal berikutnya (dihitung dari tanggal jatuh temponya), yang tidak berulang ditutup.
     */
    public function complete(): void
    {
        DB::transaction(function (): void {
            $reminder = static::query()->whereKey($this->getKey())->lockForUpdate()->firstOrFail();

            if ($reminder->selesai_pada !== null) {
                throw new LogicException(__('Pengingat ini sudah selesai.'));
            }

            $reminder->update($reminder->ulangi_bulan ? ['tanggal' => $reminder->nextDate()] : ['selesai_pada' => now()]);
        });

        $this->refresh();
    }

    // Tanggal setelah ditandai selesai; null kalau tidak berulang. Tanggal 31 tidak meluap ke bulan berikutnya.
    public function nextDate(): ?CarbonInterface
    {
        return $this->ulangi_bulan ? $this->tanggal->copy()->addMonthsNoOverflow($this->ulangi_bulan) : null;
    }

    public function isOverdue(): bool
    {
        return $this->selesai_pada === null && $this->tanggal->isPast();
    }

    // Belum selesai dan jatuh tempo paling lambat tanggal itu, termasuk yang sudah lewat.
    #[Scope]
    protected function dueBy(Builder $query, CarbonInterface $until): void
    {
        $query->whereNull('selesai_pada')->whereDate('tanggal', '<=', $until);
    }

    /**
     * @return BelongsTo<Asset, $this>
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'selesai_pada' => 'datetime',
        ];
    }
}
