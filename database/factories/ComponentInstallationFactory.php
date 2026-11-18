<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\Component;
use App\Models\ComponentInstallation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ComponentInstallation>
 */
class ComponentInstallationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'component_id' => Component::factory(),
            'asset_id' => Asset::factory(),
            'jumlah' => 1,
            'dipasang_pada' => now(),
        ];
    }
}
