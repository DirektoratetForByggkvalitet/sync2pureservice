<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AppSecret>
 */
class AppSecretFactory extends Factory {
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => fake()->uuid(),
            'displayName' => fake()->name(),
            'startDateTime' => now(),
            'endDateTime' => now()->addMonths(6),
            'appName' => fake()->name(),
            'appId' => fake()->uuid(),
            'keyType' => 'password',
            'comments' => null,
        ];
    }
}
