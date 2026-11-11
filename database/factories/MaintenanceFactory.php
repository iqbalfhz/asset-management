<?php

namespace Database\Factories;

use App\Enums\MaintenanceStatus;
use App\Models\Asset;
use App\Models\Maintenance;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Maintenance>
 */
class MaintenanceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'asset_id' => Asset::factory(),
            'status' => MaintenanceStatus::Dilaporkan,
            'keluhan' => fake()->sentence(),
        ];
    }
}
