<?php

namespace Database\Factories;

use App\Models\Release;
use App\Models\SeenRelease;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SeenRelease>
 */
class SeenReleaseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'release_id' => Release::factory(),
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ];
    }
}
