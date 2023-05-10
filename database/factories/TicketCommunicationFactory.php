<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TicketCommunication>
 */
class TicketCommunicationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => random_int(1, 10000000),
            'ticketId' => null,
            'changeId' => null,
            'text' => fake()->text(200),
            'subject' => null,
        ];
    }
}
