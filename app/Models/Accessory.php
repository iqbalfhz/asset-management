<?php

namespace App\Models;

use App\Models\Concerns\AllocatesStock;
use App\Models\Concerns\LogsChanges;
use App\Models\Scopes\CompanyScope;
use App\Models\Scopes\ManagedRoleScope;
use Database\Factories\AccessoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['company_id', 'role_id', 'nama', 'manufacturer_id', 'jumlah', 'stok_minimum', 'catatan'])]
#[ScopedBy([CompanyScope::class, ManagedRoleScope::class])]
class Accessory extends Model
{
    /** @use HasFactory<AccessoryFactory> */
    use AllocatesStock, HasFactory, LogsChanges;

    public function checkOut(Employee $employee, int $jumlah, ?string $catatan = null): AccessoryCheckout
    {
        $employee->ensureCanReceive($this);

        return $this->allocate($jumlah, [
            'employee_id' => $employee->getKey(),
            'diserahkan_pada' => now(),
            'diserahkan_oleh' => auth()->id(),
            'catatan' => $catatan,
        ]);
    }

    /**
     * @return HasMany<AccessoryCheckout, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(AccessoryCheckout::class);
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
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
