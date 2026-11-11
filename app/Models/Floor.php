<?php

namespace App\Models;

use App\Models\Concerns\LogsChanges;
use App\Models\Scopes\CompanyScope;
use Database\Factories\FloorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['building_id', 'nama'])]
class Floor extends Model
{
    /** @use HasFactory<FloorFactory> */
    use HasFactory, LogsChanges;

    protected static function booted(): void
    {
        // Lantai tidak punya company_id; ikut perusahaan gedungnya (scope Building ikut terpasang di whereHas).
        static::addGlobalScope('company', function (Builder $query): void {
            if (CompanyScope::restrictedCompanyId() !== null) {
                $query->whereHas('building');
            }
        });
    }

    /**
     * @return BelongsTo<Building, $this>
     */
    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
    }

    /**
     * @return HasMany<Room, $this>
     */
    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }
}
