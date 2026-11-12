<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Consumable;
use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Consumable>
 */
class ConsumableFactory extends Factory
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
            'role_id' => fn () => Role::firstOrCreate(['name' => 'admin_it', 'guard_name' => 'web'], ['panel' => 'admin'])->getKey(),
            'nama' => ucfirst(fake()->unique()->words(3, true)),
            'satuan' => fake()->randomElement(['pcs', 'rim', 'botol', 'kotak']),
            'stok_minimum' => 5,
        ];
    }
}
