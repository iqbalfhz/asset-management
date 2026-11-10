<?php

namespace Database\Factories;

use App\Models\Depreciation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Depreciation>
 */
class DepreciationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nama' => fake()->unique()->words(2, true),
            'jumlah_bulan' => fake()->randomElement([12, 24, 36, 48, 60]),
        ];
    }
}
