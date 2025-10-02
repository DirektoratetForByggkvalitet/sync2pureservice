<?php

namespace Database\Factories;

use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Ticket>
 */
class TicketFactory extends Factory
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
            'requestNumber' => random_int(1, 10000000),
            'assignedAgentId' => null,
            'assignedTeamId' => null,
            'assignedDepartmentId' => null,
            'userId' => 1,
            'priorityId' => 4,
            'statusId' => random_int(1, 10),
            'sourceId' => 3,
            'category1Id' => null,
            'category2Id' => null,
            'category3Id' => null,
            'ticketTypeId' => random_int(1,10),
            'visibility' => config('pureservice.visibility.invisible'),
            'emailAddress' => fake()->safeEmail(),
            'subject' => fake()->text(75),
            'description' => fake()->text(200),
            'solution' => null,
            'eFormidling' => false,
            'attachments' => [],
            'links' => [],
        ];
    }
}
