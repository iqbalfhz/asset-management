<?php

namespace Database\Factories;

use App\Models\Accessory;
use App\Models\Kit;
use App\Models\KitItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KitItem>
 */
class KitItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kit_id' => Kit::factory(),
            'item_type' => (new Accessory)->getMorphClass(),
            'item_id' => Accessory::factory(),
            'jumlah' => 1,
        ];
    }
}
