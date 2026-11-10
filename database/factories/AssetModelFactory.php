<?php

namespace Database\Factories;

use App\Models\AssetModel;
use App\Models\Category;
use App\Models\Manufacturer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssetModel>
 */
class AssetModelFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'manufacturer_id' => Manufacturer::factory(),
            'category_id' => Category::factory(),
            'nama' => ucfirst(fake()->unique()->words(2, true)),
            'nomor_model' => strtoupper(fake()->bothify('??-####')),
        ];
    }
}
