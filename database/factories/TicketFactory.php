<?php

namespace Database\Factories;

use App\Domain\Tickets\Enums\TicketChannel;
use App\Domain\Tickets\Enums\TicketPriority;
use App\Domain\Tickets\Enums\TicketStatus;
use App\Domain\Tickets\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ticket>
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
            'subject' => rtrim(fake()->sentence(6), '.'),
            'status' => TicketStatus::New,
            'priority' => TicketPriority::Normal,
            'type' => null,
            'channel' => TicketChannel::Portal,
            'requester_id' => User::factory(),
        ];
    }

    public function status(TicketStatus $status): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => $status,
            'solved_at' => in_array($status, [TicketStatus::Solved, TicketStatus::Closed], true) ? now() : null,
            'closed_at' => $status === TicketStatus::Closed ? now() : null,
        ]);
    }

    public function priority(TicketPriority $priority): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => $priority,
        ]);
    }

    public function assignedTo(User $agent): static
    {
        return $this->state(fn (array $attributes) => [
            'assignee_id' => $agent->id,
            'status' => TicketStatus::Open,
        ]);
    }
}
