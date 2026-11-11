<?php

namespace App\Models\Concerns;

use App\Models\Asset;
use App\Models\Assignment;
use Illuminate\Database\Eloquent\Model;

// Untuk tujuan serah-terima (karyawan, ruang, departemen).
trait ReceivesAssets
{
    protected static function bootReceivesAssets(): void
    {
        // Yang pernah menerima aset tidak dihapus, supaya riwayat dan BAST tetap utuh.
        static::deleting(fn (Model $model): bool => ! $model->hasReceivedAssets());
    }

    public function hasReceivedAssets(): bool
    {
        return Assignment::withoutGlobalScopes()->where('target_type', $this->getMorphClass())->where('target_id', $this->getKey())->exists()
            || Asset::withoutGlobalScopes()->where('assigned_type', $this->getMorphClass())->where('assigned_id', $this->getKey())->exists();
    }
}
