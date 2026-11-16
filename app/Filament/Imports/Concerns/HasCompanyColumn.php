<?php

namespace App\Filament\Imports\Concerns;

use App\Models\Company;
use App\Models\Scopes\CompanyScope;
use Filament\Actions\Imports\ImportColumn;
use Illuminate\Database\Eloquent\Builder;

trait HasCompanyColumn
{
    protected static function companyColumn(): ImportColumn
    {
        return ImportColumn::make('company')
            ->label(__('Perusahaan'))
            ->exampleHeader('perusahaan')
            ->example('TCM')
            ->helperText(__('Kode perusahaan aktif. Kosong = perusahaan akun Anda.'))
            ->relationship(resolveUsing: fn (string $state): ?Company => static::company($state))
            ->ignoreBlankState();
    }

    // Hanya perusahaan aktif; saat pemisahan perusahaan aktif, user yang dibatasi hanya bisa memakai perusahaannya sendiri.
    protected static function company(string $code): ?Company
    {
        $restrictedCompanyId = CompanyScope::restrictedCompanyId();

        return Company::query()
            ->active()
            ->where('kode', strtoupper($code))
            ->when($restrictedCompanyId !== null, fn (Builder $query) => $query->whereKey($restrictedCompanyId))
            ->first();
    }

    // Perusahaan baris ini: dari kolom perusahaan, atau perusahaan akun yang mengimport kalau kosong dan masih aktif.
    protected function companyId(): ?int
    {
        $code = $this->data['company'] ?? null;

        return filled($code)
            ? static::company($code)?->getKey()
            : Company::query()->active()->whereKey(auth()->user()->company_id)->value('id');
    }
}
