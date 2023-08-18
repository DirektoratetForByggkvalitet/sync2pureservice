<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

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
            'id' => Str::orderedUuid(),
            'sender_id' => null,
            'receiver_id' => null,
            'documentStandard' => fake()->text(25),
            'conversationId' => Str::uuid(),
            'content' => '{}',
            'mainDocument' => null,
            'attachments' => [],
        ];
    }
}
