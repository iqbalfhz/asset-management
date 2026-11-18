<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use LogicException;

// Baris barang yang keluar lalu kembali; kolom penutupnya ditentukan model lewat RELEASED_AT dan RELEASED_BY.
trait ReleasesAllocation
{
    #[Scope]
    protected function open(Builder $query): void
    {
        $query->whereNull($query->qualifyColumn(static::RELEASED_AT));
    }

    public function isOpen(): bool
    {
        return $this->getAttribute(static::RELEASED_AT) === null;
    }

    public function release(): void
    {
        DB::transaction(function (): void {
            $line = static::withoutGlobalScopes()->whereKey($this->getKey())->lockForUpdate()->firstOrFail();

            if (! $line->isOpen()) {
                throw new LogicException(__('Barang ini sudah kembali.'));
            }

            $line->update([static::RELEASED_AT => now(), static::RELEASED_BY => auth()->id()]);
        });

        $this->refresh();
    }
}
