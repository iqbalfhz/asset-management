<?php

namespace App\Models;

use Database\Factories\AssetModelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['manufacturer_id', 'category_id', 'depreciation_id', 'nama', 'nomor_model', 'foto'])]
class AssetModel extends Model
{
    /** @use HasFactory<AssetModelFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Manufacturer, $this>
     */
    public function manufacturer(): BelongsTo
    {
        return $this->belongsTo(Manufacturer::class);
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsTo<Depreciation, $this>
     */
    public function depreciation(): BelongsTo
    {
        return $this->belongsTo(Depreciation::class);
    }
}
