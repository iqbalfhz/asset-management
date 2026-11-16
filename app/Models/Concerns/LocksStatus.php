<?php

namespace App\Models\Concerns;

use BackedEnum;
use Filament\Support\Contracts\HasLabel;
use LogicException;

trait LocksStatus
{
    // Status dibaca ulang dengan kunci baris di dalam transaksi, supaya dua admin tidak memproses data yang sama bersamaan.
    private function ensureStatus(BackedEnum&HasLabel $expected): void
    {
        $current = static::withoutGlobalScopes()->whereKey($this->getKey())->lockForUpdate()->value('status');

        if ($current !== $expected) {
            throw new LogicException(__('Statusnya sudah :status, jadi aksi ini tidak bisa dilakukan.', ['status' => $current->getLabel()]));
        }
    }
}
