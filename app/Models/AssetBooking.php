<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Models\Concerns\LogsChanges;
use Carbon\CarbonInterface;
use Database\Factories\AssetBookingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use LogicException;

#[Fillable(['asset_id', 'employee_id', 'mulai', 'selesai', 'keperluan', 'dibatalkan_pada', 'dibatalkan_oleh', 'alasan_batal', 'created_by'])]
class AssetBooking extends Model
{
    /** @use HasFactory<AssetBookingFactory> */
    use HasFactory, LogsChanges;

    protected static function booted(): void
    {
        // Admin melihat pesanan sebatas hak lihat asetnya.
        static::addGlobalScope('asset', fn (Builder $query) => $query->whereHas('asset'));
    }

    /**
     * Pesan barang bersama; langsung terpesan kalau jadwalnya kosong. Aset dikunci supaya dua pesanan bersamaan tidak bentrok.
     */
    public static function book(Asset $asset, Employee $employee, CarbonInterface $mulai, CarbonInterface $selesai, string $keperluan): self
    {
        if ($selesai->lte($mulai)) {
            throw new LogicException(__('Jam selesai harus setelah jam mulai.'));
        }

        if ($mulai->lt(now()->startOfMinute())) {
            throw new LogicException(__('Jam mulai sudah lewat.'));
        }

        return DB::transaction(function () use ($asset, $employee, $mulai, $selesai, $keperluan): self {
            $locked = Asset::withoutGlobalScopes()->with('status')->whereKey($asset->getKey())->lockForUpdate()->firstOrFail();

            if (! $locked->bookable || $locked->status->nama !== Status::TERSEDIA) {
                throw new LogicException(__('Aset :kode tidak bisa dipesan.', ['kode' => $locked->kode]));
            }

            $employee->ensureCanReceive($locked);

            $clash = static::withoutGlobalScopes()
                ->active()
                ->where('asset_id', $locked->getKey())
                ->where('mulai', '<', $selesai)
                ->where('selesai', '>', $mulai)
                ->with(['employee' => fn (BelongsTo $query) => $query->withoutGlobalScopes()])
                ->first();

            if ($clash) {
                throw new LogicException(__(':kode sudah dipesan :nama pada :waktu.', ['kode' => $locked->kode, 'nama' => $clash->employee->nama, 'waktu' => $clash->period()]));
            }

            return static::query()->create([
                'asset_id' => $locked->getKey(),
                'employee_id' => $employee->getKey(),
                'mulai' => $mulai,
                'selesai' => $selesai,
                'keperluan' => $keperluan,
                'created_by' => auth()->id(),
            ]);
        });
    }

    public function cancel(?string $alasan = null): void
    {
        if ($this->dibatalkan_pada !== null) {
            throw new LogicException(__('Pesanan ini sudah dibatalkan.'));
        }

        if ($this->selesai->isPast()) {
            throw new LogicException(__('Pesanan yang sudah selesai tidak bisa dibatalkan.'));
        }

        $this->update(['dibatalkan_pada' => now(), 'dibatalkan_oleh' => auth()->id(), 'alasan_batal' => $alasan]);
    }

    public function status(): BookingStatus
    {
        return match (true) {
            $this->dibatalkan_pada !== null => BookingStatus::Dibatalkan,
            $this->mulai->isFuture() => BookingStatus::AkanDatang,
            $this->selesai->isFuture() => BookingStatus::Berjalan,
            default => BookingStatus::Selesai,
        };
    }

    public function isCancellable(): bool
    {
        return in_array($this->status(), [BookingStatus::AkanDatang, BookingStatus::Berjalan], true);
    }

    // Contoh "20 Nov 2026, 09:00–12:00"; kalau beda hari, tanggal selesai ikut ditulis.
    public function period(): string
    {
        return $this->mulai->isSameDay($this->selesai)
            ? $this->mulai->translatedFormat('j M Y, H:i').'–'.$this->selesai->format('H:i')
            : $this->mulai->translatedFormat('j M Y, H:i').' – '.$this->selesai->translatedFormat('j M Y, H:i');
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->whereNull('dibatalkan_pada');
    }

    /**
     * @return BelongsTo<Asset, $this>
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /**
     * Pemesan dibaca tanpa scope perusahaan: pesanan sudah lolos hak lihat lewat asetnya.
     *
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class)->withoutGlobalScopes();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'mulai' => 'datetime',
            'selesai' => 'datetime',
            'dibatalkan_pada' => 'datetime',
        ];
    }
}
