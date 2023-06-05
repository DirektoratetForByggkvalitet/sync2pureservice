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
            'sender' => fake()->text(50),
            'sender_id' => null,
            'receiver' => fake()->text(50),
            'receiver_id' => null,
            'documentId' => fake()->text(50),
            'documentStandard' => fake()->text(25),
            'conversationId' => fake()->text(50),
            'content' => '{}',
            'attachments' => null,
        ];
    }
}
