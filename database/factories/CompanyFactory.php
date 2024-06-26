<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Company>
 */
class CompanyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'id' => null,
            'name' => fake()->name(),
            'organizationNumber' => '000000000',
            'companyNumber' => null,
            'website' => 'https://'.fake()->safeEmailDomain(),
            'email' => fake()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'notes' => null,
            'category' => null,
        ];
    }
}
