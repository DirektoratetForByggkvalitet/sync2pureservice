<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Message>
 */
class MessageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => uuid_create(),
            'sender_id' => null,
            'receiver_id' => null,
            'documentStandard' => fake()->text(25),
            'conversationId' => fake()->text(50),
            'content' => '{}',
            'mainDocument' => null,
            'attachments' => [],
        ];
    }
}
