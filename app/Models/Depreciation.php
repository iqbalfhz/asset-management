<?php

namespace App\Models;

use Database\Factories\DepreciationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['nama', 'jumlah_bulan'])]
class Depreciation extends Model
{
    /** @use HasFactory<DepreciationFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'jumlah_bulan' => 'integer',
        ];
    }
}
