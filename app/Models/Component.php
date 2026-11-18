<?php

namespace App\Models;

use App\Models\Concerns\AllocatesStock;
use App\Models\Concerns\LogsChanges;
use App\Models\Scopes\CompanyScope;
use App\Models\Scopes\ManagedRoleScope;
use Database\Factories\ComponentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['company_id', 'role_id', 'nama', 'manufacturer_id', 'jumlah', 'stok_minimum', 'catatan'])]
#[ScopedBy([CompanyScope::class, ManagedRoleScope::class])]
class Component extends Model
{
    /** @use HasFactory<ComponentFactory> */
    use AllocatesStock, HasFactory, LogsChanges;

    public function install(Asset $asset, int $jumlah, ?string $catatan = null): ComponentInstallation
    {
        $asset->ensureCanReceive($this);

        return $this->allocate($jumlah, [
            'asset_id' => $asset->getKey(),
            'dipasang_pada' => now(),
            'dipasang_oleh' => auth()->id(),
            'catatan' => $catatan,
        ]);
    }

    /**
     * @return HasMany<ComponentInstallation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(ComponentInstallation::class);
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Role admin yang mengelola komponen ini.
     *
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * @return BelongsTo<Manufacturer, $this>
     */
    public function manufacturer(): BelongsTo
    {
        return $this->belongsTo(Manufacturer::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'jumlah' => 'integer',
            'stok_minimum' => 'integer',
        ];
    }
}
