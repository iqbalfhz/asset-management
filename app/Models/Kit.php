<?php

namespace App\Models;

use App\Models\Concerns\LogsChanges;
use App\Models\Scopes\CompanyScope;
use Database\Factories\KitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use LogicException;

#[Fillable(['company_id', 'nama', 'keterangan'])]
#[ScopedBy([CompanyScope::class])]
class Kit extends Model
{
    /** @use HasFactory<KitFactory> */
    use HasFactory, LogsChanges;

    /**
     * Kalau satu barang gagal, tidak ada yang tersimpan; nomor BAST dikembalikan kalau paket berisi aset.
     *
     * @param  array<int|string, array<int, int|string>>  $assetIds  aset terpilih per baris model aset (id baris => id aset)
     * @param  array{tanggal: string, kondisi: string, catatan?: ?string, expected_return_at?: ?string}  $details  isian BAST aset
     */
    public function handOver(Employee $employee, array $assetIds, array $details): ?string
    {
        return DB::transaction(function () use ($employee, $assetIds, $details): ?string {
            $employee->ensureCanReceive($this);

            $note = filled($details['catatan'] ?? null) ? $details['catatan'] : __('Paket :nama', ['nama' => $this->nama]);
            $assets = new Collection;

            foreach ($this->items()->with('item')->get() as $line) {
                $item = $line->item ?? throw new LogicException(__('Sebagian isi paket tidak bisa Anda serahkan.'));

                match (true) {
                    $item instanceof AssetModel => $assets = $assets->merge($this->chosenAssets($line, $assetIds[$line->getKey()] ?? [])),
                    $item instanceof Accessory => $item->checkOut($employee, $line->jumlah, $note),
                    $item instanceof License => $item->assignSeat($employee),
                    $item instanceof Consumable => $item->issue($line->jumlah, $employee, ['catatan' => $note]),
                };
            }

            return $assets->isEmpty() ? null : Assignment::handOver($assets, $employee, ['catatan' => $note] + $details);
        });
    }

    /**
     * Aset yang dipilih harus dari model baris itu dan perusahaan paket, sebanyak jumlahnya. Statusnya dicek oleh serah-terima.
     *
     * @param  array<int, int|string>  $ids
     * @return Collection<int, Asset>
     */
    private function chosenAssets(KitItem $line, array $ids): Collection
    {
        $assets = Asset::query()
            ->whereKey($ids)
            ->where('asset_model_id', $line->item_id)
            ->where('company_id', $this->company_id)
            ->get();

        if (count(array_unique($ids)) !== $line->jumlah || $assets->count() !== $line->jumlah) {
            throw new LogicException(__('Pilih :jumlah aset :model.', ['jumlah' => $line->jumlah, 'model' => $line->item->fullName()]));
        }

        return $assets;
    }

    // Paket berisi aset butuh isian BAST saat diserahkan.
    public function hasAssets(): bool
    {
        return $this->items->contains('item_type', (new AssetModel)->getMorphClass());
    }

    /**
     * @return HasMany<KitItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(KitItem::class);
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
