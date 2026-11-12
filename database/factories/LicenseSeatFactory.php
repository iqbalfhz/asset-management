<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\License;
use App\Models\LicenseSeat;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LicenseSeat>
 */
class LicenseSeatFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'license_id' => License::factory(),
            'employee_id' => Employee::factory(),
            'dipasang_pada' => now(),
        ];
    }
}
