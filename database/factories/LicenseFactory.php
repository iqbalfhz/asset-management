<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\License;
use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<License>
 */
class LicenseFactory extends Factory
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
            'nama_software' => fake()->randomElement(['Microsoft 365 Business', 'Adobe Acrobat Pro', 'AutoCAD LT', 'Windows 11 Pro']),
            'product_key' => strtoupper(fake()->bothify('?????-?????-?????-?????-?????')),
            'jumlah_seat' => 5,
            'tgl_habis' => now()->addYear()->toDateString(),
        ];
    }
}
