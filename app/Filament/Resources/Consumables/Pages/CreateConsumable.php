<?php

namespace App\Filament\Resources\Consumables\Pages;

use App\Filament\Resources\Consumables\ConsumableResource;
use App\Models\Consumable;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class CreateConsumable extends CreateRecord
{
    protected static string $resource = ConsumableResource::class;

    /**
     * Stok awal dicatat sebagai transaksi masuk, supaya riwayat dan stok selalu cocok.
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return DB::transaction(function () use ($data): Consumable {
            $initialStock = (int) Arr::pull($data, 'stok_awal', 0);
            $consumable = Consumable::query()->create($data);

            if ($initialStock > 0) {
                $consumable->receive($initialStock, ['catatan' => __('Stok awal')]);
            }

            return $consumable;
        });
    }
}
