<?php

namespace Database\Factories;

use App\Domain\Tickets\Enums\TicketChannel;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Tickets\Models\TicketMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketMessage>
 */
class TicketMessageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ticket_id' => Ticket::factory(),
            'author_id' => User::factory(),
            'body' => '<p>'.fake()->paragraph().'</p>',
            'is_internal' => false,
            'channel' => TicketChannel::Portal,
        ];
    }

    public function internal(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_internal' => true,
            'channel' => TicketChannel::Agent,
        ]);
    }
}
