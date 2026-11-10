<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nama' => ucfirst(fake()->unique()->word()),
            'kode' => strtoupper(fake()->unique()->lexify('???')),
            'role_id' => fn () => Role::firstOrCreate(['name' => 'admin_it', 'guard_name' => 'web'], ['panel' => 'admin'])->id,
            'template_spesifikasi' => ['Prosesor', 'RAM', 'Penyimpanan'],
            'warna' => fake()->hexColor(),
        ];
    }
}
