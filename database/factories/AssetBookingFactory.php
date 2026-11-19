<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\AssetBooking;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssetBooking>
 */
class AssetBookingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'asset_id' => Asset::factory(),
            'employee_id' => Employee::factory(),
            'mulai' => now()->addDay()->setTime(9, 0),
            'selesai' => now()->addDay()->setTime(12, 0),
            'keperluan' => fake()->sentence(),
        ];
    }
}
