<?php

namespace Database\Factories;

use App\Models\Label;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Label>
 */
class LabelFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company().' Records';

        return [
            'discogs_id' => fake()->unique()->numberBetween(1, 9999999),
            'name' => $name,
            'normalized_name' => Str::lower($name),
        ];
    }
}
