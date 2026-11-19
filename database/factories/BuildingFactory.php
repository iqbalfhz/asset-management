<?php

namespace Database\Factories;

use App\Models\Building;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Building>
 */
class BuildingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'nama' => 'Gedung '.fake()->unique()->lexify('???'),
        ];
    }
}
