<?php

namespace App\Models;

use Database\Factories\KitItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['kit_id', 'item_type', 'item_id', 'jumlah'])]
class KitItem extends Model
{
    /** @use HasFactory<KitItemFactory> */
    use HasFactory;

    /**
     * Jenis barang yang bisa masuk paket.
     *
     * @return array<class-string<Model>, string>
     */
    public static function types(): array
    {
        return [
            AssetModel::class => __('Model aset'),
            Accessory::class => __('Aksesori'),
            License::class => __('Lisensi'),
            Consumable::class => __('Barang habis pakai'),
        ];
    }

    // Contoh: "2 × Mouse Logitech". Barang yang tidak terlihat oleh admin ini (role lain) tidak disebut namanya.
    public function label(): string
    {
        $name = match (true) {
            $this->item instanceof AssetModel => $this->item->fullName(),
            $this->item instanceof License => $this->item->nama_software,
            $this->item === null => __('barang role lain'),
            default => $this->item->nama,
        };

        return "{$this->jumlah} × {$name}";
    }

    /**
     * @return BelongsTo<Kit, $this>
     */
    public function kit(): BelongsTo
    {
        return $this->belongsTo(Kit::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function item(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'jumlah' => 'integer',
        ];
    }
}
