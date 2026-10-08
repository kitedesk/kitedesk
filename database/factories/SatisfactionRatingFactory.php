<?php

namespace Database\Factories;

use App\Domain\Tickets\Enums\TicketStatus;
use App\Domain\Tickets\Models\SatisfactionRating;
use App\Domain\Tickets\Models\Ticket;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SatisfactionRating>
 */
class SatisfactionRatingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ticket_id' => Ticket::factory()->status(TicketStatus::Solved),
            'user_id' => fn (array $attributes) => Ticket::query()->whereKey($attributes['ticket_id'])->value('requester_id'),
            'sent_at' => now(),
        ];
    }

    public function rated(int $score, ?string $comment = null): static
    {
        return $this->state(fn (array $attributes) => [
            'score' => $score,
            'comment' => $comment,
            'rated_at' => now(),
        ]);
    }
}
