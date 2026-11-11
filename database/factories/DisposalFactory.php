<?php

namespace Database\Factories;

use App\Enums\DisposalMethod;
use App\Enums\DisposalStatus;
use App\Models\Asset;
use App\Models\Disposal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Disposal>
 */
class DisposalFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'asset_id' => Asset::factory(),
            'alasan' => fake()->sentence(),
            'metode' => DisposalMethod::Dibuang,
            'diajukan_oleh' => User::factory(),
            'status' => DisposalStatus::Diajukan,
        ];
    }
}
