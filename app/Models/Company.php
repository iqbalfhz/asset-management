<?php

namespace App\Models;

use App\Models\Concerns\LogsChanges;
use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['nama', 'kode', 'is_active'])]
class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use HasFactory, LogsChanges;

    // Perusahaan nonaktif tidak menerima data baru; datanya yang sudah ada tetap bisa dipakai.
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    // Kode dipakai sebagai awalan kode aset, jadi selalu disimpan huruf besar.
    protected function kode(): Attribute
    {
        return Attribute::make(set: fn (string $value): string => strtoupper($value));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
