<?php

namespace Database\Factories;

use App\Enums\AssetRequestStatus;
use App\Models\AssetRequest;
use App\Models\Category;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssetRequest>
 */
class AssetRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'category_id' => Category::factory(),
            'alasan' => fake()->sentence(),
            'status' => AssetRequestStatus::Diajukan,
        ];
    }
}
