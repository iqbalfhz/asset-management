<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\AssetReminder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssetReminder>
 */
class AssetReminderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'asset_id' => Asset::factory(),
            'jenis' => fake()->randomElement(['Pajak STNK', 'Servis berkala', 'Akhir kontrak sewa']),
            'tanggal' => today()->addMonth()->toDateString(),
        ];
    }
}
