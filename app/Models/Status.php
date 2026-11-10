<?php

namespace App\Models;

use App\Enums\StatusType;
use Database\Factories\StatusFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use LogicException;

#[Fillable(['nama', 'tipe', 'warna'])]
class Status extends Model
{
    /** @use HasFactory<StatusFactory> */
    use HasFactory;

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
