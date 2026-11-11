<?php

namespace Database\Factories;

use App\Enums\AuditResult;
use App\Models\Asset;
use App\Models\Audit;
use App\Models\AuditItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditItem>
 */
class AuditItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'audit_id' => Audit::factory(),
            'asset_id' => Asset::factory(),
            'hasil' => AuditResult::Menunggu,
        ];
    }
}
