<?php

namespace Database\Factories;

use App\Models\Accessory;
use App\Models\Company;
use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Accessory>
 */
class AccessoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'role_id' => fn () => Role::firstOrCreate(['name' => 'admin_it', 'guard_name' => 'web'], ['panel' => 'admin'])->getKey(),
            'nama' => ucfirst(fake()->unique()->words(2, true)),
            'jumlah' => 10,
            'stok_minimum' => 0,
        ];
    }
}
