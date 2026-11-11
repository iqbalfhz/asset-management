<?php

namespace Database\Factories;

use App\Enums\AssignmentStatus;
use App\Enums\AssignmentType;
use App\Models\Asset;
use App\Models\Assignment;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Assignment>
 */
class AssignmentFactory extends Factory
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
            'target_type' => (new Employee)->getMorphClass(),
            'target_id' => Employee::factory(),
            'jenis' => AssignmentType::Keluar,
            'tanggal' => now()->toDateString(),
            'kondisi' => 'Baik',
            'no_bast' => 'BAST/TCM/'.now()->year.'/'.fake()->unique()->numerify('####'),
            'status' => AssignmentStatus::Menunggu,
            'created_by' => User::factory(),
        ];
    }
}
