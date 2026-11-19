<?php

namespace Database\Factories;

use App\Models\Manufacturer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Manufacturer>
 */
class ManufacturerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nama' => fake()->unique()->company(),
            'website' => fake()->url(),
            'kontak_support' => fake()->safeEmail(),
        ];
    }
}
