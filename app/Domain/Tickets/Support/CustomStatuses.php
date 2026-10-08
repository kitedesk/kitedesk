<?php

namespace App\Domain\Tickets\Support;

use App\Domain\Tickets\Enums\TicketStatus;
use App\Domain\Tickets\Models\CustomStatus;
use App\Domain\Tickets\Models\Ticket;
use Illuminate\Database\Eloquent\Collection;

/**
 * The admin-defined statuses, loaded once per request, and the rules that keep a ticket's
 * status and its category in step.
 *
 * Choosing a status moves the ticket into that status's category. Code that only changes the
 * category (a customer reply reopening the ticket, auto-close, merging) moves the ticket to
 * the category's default status.
 */
class CustomStatuses
{
    /**
     * @var Collection<int, CustomStatus>|null
     */
    private ?Collection $statuses = null;

    /**
     * Every status, in board order.
     *
     * @return Collection<int, CustomStatus>
     */
    public static function all(): Collection
    {
        $instance = app(self::class);

        return $instance->statuses ??= CustomStatus::query()->ordered()->get();
    }

    /**
     * @return Collection<int, CustomStatus>
     */
    public static function active(): Collection
    {
        return self::all()->where('is_active', true)->values();
    }

    public static function find(?int $id): ?CustomStatus
    {
        return $id === null ? null : self::all()->firstWhere('id', $id);
    }

    /**
     * The status a ticket gets when it moves into the category without one being chosen.
     */
    public static function defaultFor(TicketStatus $category): CustomStatus
    {
        $default = self::all()->first(fn (CustomStatus $status): bool => $status->is_default && $status->category === $category);

        if ($default !== null) {
            return $default;
        }

        $default = new CustomStatus(['category' => $category]);
        $default->is_default = true;
        $default->save();

        return $default;
    }

    /**
     * Every status for select options and labels, including inactive ones so older
     * activity still has a name.
     *
     * @return list<array{id: int, name: string, color: string, category: string, is_active: bool, is_default: bool}>
     */
    public static function options(): array
    {
        return array_values(self::all()
            ->map(fn (CustomStatus $status): array => [...$status->toSummary(), 'is_active' => $status->is_active, 'is_default' => $status->is_default])
            ->all());
    }

    /**
     * Forget the statuses loaded during this request.
     */
    public static function flush(): void
    {
        app(self::class)->statuses = null;
    }

    /**
     * Move the ticket into the category of the status that was just chosen.
     */
    public static function applyChosenStatus(Ticket $ticket): void
    {
        $status = $ticket->isDirty('ticket_status_id') ? self::find($ticket->ticket_status_id) : null;

        if ($status !== null) {
            $ticket->status = $status->category;
        }
    }

    /**
     * Give the ticket its category's default status when its status no longer matches.
     */
    public static function matchCategory(Ticket $ticket): void
    {
        // New tickets may rely on the column default.
        $category = $ticket->getAttribute('status') ?? TicketStatus::New;

        if (self::find($ticket->ticket_status_id)?->category !== $category) {
            $ticket->ticket_status_id = self::defaultFor($category)->id;
        }
    }

    /**
     * Applied whenever a ticket is saved, so every way of changing it keeps the two in step.
     * A status set in the same change wins over the category.
     */
    public static function sync(Ticket $ticket): void
    {
        self::applyChosenStatus($ticket);
        self::matchCategory($ticket);
    }
}
