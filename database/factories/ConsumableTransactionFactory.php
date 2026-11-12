<?php

namespace Database\Factories;

use App\Enums\StockMovement;
use App\Models\Consumable;
use App\Models\ConsumableTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConsumableTransaction>
 */
class ConsumableTransactionFactory extends Factory
{
    /**
     * Riwayat saja; stok barang tidak ikut berubah. Pakai Consumable::receive() atau issue() untuk transaksi sungguhan.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'consumable_id' => Consumable::factory(),
            'jenis' => StockMovement::Masuk,
            'jumlah' => 10,
            'stok_sesudah' => 10,
            'tanggal' => today()->toDateString(),
        ];
    }
}
