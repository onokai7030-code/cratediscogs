<?php

namespace Database\Factories;

use App\Models\Release;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Release>
 */
class ReleaseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'discogs_id' => fake()->unique()->numberBetween(1, 9999999),
            'artist' => fake()->name(),
            'title' => fake()->sentence(3),
            'catalog_number' => strtoupper(fake()->bothify('??-###')),
            'year' => fake()->numberBetween(1980, 2026),
            'country' => fake()->countryCode(),
            'formats' => ['Vinyl'],
            'genres' => ['Electronic'],
            'have' => fake()->numberBetween(0, 1000),
            'want' => fake()->numberBetween(0, 1000),
            'url' => fake()->url(),
        ];
    }
}
