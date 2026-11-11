<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\Attachment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attachment>
 */
class AttachmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'attachable_type' => (new Asset)->getMorphClass(),
            'attachable_id' => Asset::factory(),
            'nama_file' => 'faktur.pdf',
            'path' => 'attachments/'.fake()->uuid().'.pdf',
            'ukuran' => 1024,
        ];
    }
}
