<?php

namespace Database\Factories;

use App\Models\Accessory;
use App\Models\AccessoryCheckout;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccessoryCheckout>
 */
class AccessoryCheckoutFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'accessory_id' => Accessory::factory(),
            'employee_id' => Employee::factory(),
            'jumlah' => 1,
            'diserahkan_pada' => now(),
        ];
    }
}
