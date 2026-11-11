<?php

namespace Database\Factories;

use App\Enums\StatusType;
use App\Models\Asset;
use App\Models\Category;
use App\Models\Company;
use App\Models\Status;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Asset>
 */
class AssetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'nama' => ucfirst(fake()->words(2, true)),
            'category_id' => Category::factory(),
            'status_id' => fn () => Status::firstOrCreate(['nama' => Status::TERSEDIA], ['tipe' => StatusType::SiapDipakai, 'warna' => '#2f6b55'])->getKey(),
            'no_seri' => strtoupper(fake()->bothify('SN-########')),
            'tgl_beli' => fake()->dateTimeBetween('-3 years'),
            'harga_beli' => fake()->numberBetween(500_000, 25_000_000),
        ];
    }
}
