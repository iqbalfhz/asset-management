<?php

namespace App\Models;

use App\Enums\StatusType;
use App\Models\Concerns\LogsChanges;
use Database\Factories\StatusFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use LogicException;

#[Fillable(['nama', 'tipe', 'warna'])]
class Status extends Model
{
    /** @use HasFactory<StatusFactory> */
    use HasFactory, LogsChanges;

    // Nama status inti dikunci, jadi aman dipakai sebagai penanda di kode.
    public const string DISIAPKAN = 'Disiapkan';

    public const string TERSEDIA = 'Tersedia';

    public const string DIPAKAI = 'Dipakai';

    public const string DALAM_PERBAIKAN = 'Dalam Perbaikan';

    public const string RUSAK = 'Rusak';

    public const string HILANG = 'Hilang';

    public const string DIHAPUSKAN = 'Dihapuskan';

    public static function named(string $nama): self
    {
        return static::query()->where('nama', $nama)->firstOrFail();
    }

    protected static function booted(): void
    {
        // Status inti dipakai oleh transaksi aset, jadi nama dan tipenya dikunci.
        static::updating(function (Status $status): void {
            if ($status->getOriginal('is_core') && $status->isDirty(['nama', 'tipe'])) {
                throw new LogicException(__('Nama dan tipe status inti tidak bisa diubah.'));
            }
        });

        static::deleting(function (Status $status): void {
            if ($status->getOriginal('is_core')) {
                throw new LogicException(__('Status inti tidak bisa dihapus.'));
            }
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipe' => StatusType::class,
            'is_core' => 'boolean',
        ];
    }
}
