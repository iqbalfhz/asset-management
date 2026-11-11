<?php

namespace Database\Factories;

use App\Enums\AuditStatus;
use App\Models\Audit;
use App\Models\Role;
use App\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Audit>
 */
class AuditFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'room_id' => Room::factory(),
            'role_id' => fn () => Role::firstOrCreate(['name' => 'admin_it', 'guard_name' => 'web'], ['panel' => 'admin'])->getKey(),
            'status' => AuditStatus::Berjalan,
            'mulai_pada' => now(),
        ];
    }
}
