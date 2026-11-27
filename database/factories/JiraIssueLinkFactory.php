<?php

namespace Database\Factories;

use App\Domain\Jira\Enums\StatusCategory;
use App\Domain\Jira\Models\JiraIssueLink;
use App\Domain\Tickets\Models\Ticket;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An issue on the "ENG" project of example.atlassian.net, in To Do.
 *
 * @extends Factory<JiraIssueLink>
 */
class JiraIssueLinkFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $number = fake()->unique()->numberBetween(1, 99_999);

        return [
            'ticket_id' => Ticket::factory(),
            'issue_id' => (string) (10_000 + $number),
            'issue_key' => "ENG-{$number}",
            'summary' => fake()->sentence(4),
            'issue_type' => 'Bug',
            'status_name' => 'To Do',
            'status_category' => StatusCategory::ToDo,
            'url' => "https://example.atlassian.net/browse/ENG-{$number}",
            'last_synced_at' => now(),
        ];
    }

    public function inCategory(StatusCategory $category, ?string $statusName = null): static
    {
        return $this->state(fn (array $attributes) => [
            'status_category' => $category,
            'status_name' => $statusName ?? match ($category) {
                StatusCategory::ToDo => 'To Do',
                StatusCategory::InProgress => 'In Progress',
                StatusCategory::Done => 'Done',
            },
        ]);
    }
}
