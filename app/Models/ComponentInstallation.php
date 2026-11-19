<?php

namespace App\Models;

use App\Models\Concerns\LogsChanges;
use App\Models\Concerns\ReleasesAllocation;
use Database\Factories\ComponentInstallationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['component_id', 'asset_id', 'jumlah', 'dipasang_pada', 'dipasang_oleh', 'dilepas_pada', 'dilepas_oleh', 'catatan'])]
class ComponentInstallation extends Model
{
    /** @use HasFactory<ComponentInstallationFactory> */
    use HasFactory, LogsChanges, ReleasesAllocation;

    public const string OPENED_AT = 'dipasang_pada';

    public const string RELEASED_AT = 'dilepas_pada';

    public const string RELEASED_BY = 'dilepas_oleh';

    protected static function booted(): void
    {
        // Ikut hak lihat komponennya (perusahaan dan role pengelola).
        static::addGlobalScope('component', fn (Builder $query) => $query->whereHas('component'));
    }

    /**
     * @return BelongsTo<Component, $this>
     */
    public function component(): BelongsTo
    {
        return $this->belongsTo(Component::class);
    }

    /**
     * Aset dibaca tanpa scope kategori: komponen IT bisa terpasang di aset yang dikelola role lain.
     *
     * @return BelongsTo<Asset, $this>
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class)->withoutGlobalScopes();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function installer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dipasang_oleh');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'jumlah' => 'integer',
            'dipasang_pada' => 'datetime',
            'dilepas_pada' => 'datetime',
        ];
    }
}
