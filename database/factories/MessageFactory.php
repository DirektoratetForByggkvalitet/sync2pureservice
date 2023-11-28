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
            'sender_id' => null,
            'receiver_id' => null,
            'content' => '[]',
            'mainDocument' => null,
            'processIdentifier' => config('eformidling.process_pre').
                config('eformidling.out.type').':'.
                config('eformidling.out.process').
                config('eformidling.process_post'),
            'conversationId' => Str::orderedUuid()->toString(),
            'messageId' => Str::orderedUuid()->toString(),
            'documentType' => config('eformidling.out.type'),
            'documentStandard' => config('eformidling.out.standard'),
        ];
    }
}
