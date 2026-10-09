<?php

namespace App\Domain\Tickets\Support;

use App\Domain\Entitlements\Enums\Feature;
use App\Domain\Entitlements\Support\PlanLimits;
use App\Domain\Support\Models\Setting;
use App\Domain\Tickets\Enums\TicketStatus;
use App\Domain\Tickets\Models\Ticket;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * The customer satisfaction (CSAT) survey: whether it is on, and how long after a ticket is
 * solved it goes out. Only tickets solved after it was switched on are surveyed.
 */
final readonly class SatisfactionSurvey
{
    public const string SETTING = 'satisfaction';

    public const int DEFAULT_DELAY_HOURS = 24;

    /**
     * Delays offered on the settings page, in hours.
     */
    public const array DELAYS = [0, 1, 4, 24, 48, 72];

    public function __construct(
        public bool $enabled = false,
        public int $delayHours = self::DEFAULT_DELAY_HOURS,
        public ?CarbonImmutable $enabledSince = null,
    ) {}

    /**
     * The saved settings. The survey is off while the plan leaves it out.
     */
    public static function current(): self
    {
        $setting = Setting::get(self::SETTING);
        $setting = is_array($setting) ? $setting : [];
        $enabledSince = $setting['enabled_since'] ?? null;

        return new self(
            (bool) ($setting['enabled'] ?? false) && PlanLimits::allows(Feature::Satisfaction),
            is_int($setting['delay_hours'] ?? null) ? $setting['delay_hours'] : self::DEFAULT_DELAY_HOURS,
            is_string($enabledSince) ? CarbonImmutable::parse($enabledSince) : null,
        );
    }

    /**
     * The settings to store when an admin saves the page: switching it on starts the clock.
     */
    public function update(bool $enabled, int $delayHours): self
    {
        return new self(
            $enabled,
            $delayHours,
            $enabled ? ($this->enabled ? $this->enabledSince : CarbonImmutable::now()) : null,
        );
    }

    public function save(): void
    {
        Setting::put(self::SETTING, [
            'enabled' => $this->enabled,
            'delay_hours' => $this->delayHours,
            'enabled_since' => $this->enabledSince?->toIso8601String(),
        ]);
    }

    /**
     * Whether the person may rate the ticket in the portal: the survey is on and they are the
     * requester of a ticket that is solved (and not a merged duplicate).
     */
    public function canRate(Ticket $ticket, User $user): bool
    {
        return $this->enabled
            && $ticket->requester_id === $user->id
            && $user->isCustomer()
            && self::isRateable($ticket);
    }

    /**
     * Solved and closed tickets can be rated; reopening one pauses its survey.
     */
    public static function isRateable(Ticket $ticket): bool
    {
        return in_array($ticket->status, [TicketStatus::Solved, TicketStatus::Closed], true)
            && $ticket->merged_into_id === null;
    }

    /**
     * What the rating box on a request page needs, or null when the viewer can't rate the ticket.
     *
     * @return array{score: int|null, comment: string|null}|null
     */
    public function formFor(Ticket $ticket, User $viewer): ?array
    {
        if (! $this->canRate($ticket, $viewer)) {
            return null;
        }

        $rating = $ticket->satisfactionRating;

        return ['score' => $rating?->score, 'comment' => $rating?->comment];
    }
}
