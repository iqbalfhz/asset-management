<?php

namespace Database\Factories;

use App\Enums\StatusType;
use App\Models\Status;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Status>
 */
class StatusFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nama' => ucfirst(fake()->unique()->words(2, true)),
            'tipe' => fake()->randomElement(StatusType::cases()),
            'warna' => fake()->hexColor(),
        ];
    }
}
