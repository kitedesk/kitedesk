<?php

namespace App\Domain\Reports;

use App\Domain\Accounts\Models\Group;
use App\Domain\Tickets\Enums\TicketChannel;
use App\Domain\Tickets\Models\SatisfactionRating;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Tickets\Models\TicketCategory;
use App\Domain\Tickets\Support\TicketFilters;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

/**
 * Ticket volume and speed for a date range: what was created and solved in the range, how
 * fast tickets got a first reply and a resolution, how many kept their SLA, and how satisfied
 * customers were (the share of 4–5 star ratings given in the range).
 *
 * Times are calendar minutes. Medians are computed in PHP so the report works the same on
 * SQLite, PostgreSQL and MySQL; tickets are streamed to keep memory flat, and the result is
 * cached for a few minutes (see `cached()`).
 *
 * @phpstan-type Row array{key: string, label: string, created: int, solved: int, median_first_response_minutes: int|null, median_resolution_minutes: int|null, satisfaction: float|null, satisfaction_responses: int}
 * @phpstan-type Report array{
 *     range: array{from: string, to: string},
 *     totals: array{created: int, solved: int, backlog: int, median_first_response_minutes: int|null, median_resolution_minutes: int|null, sla_compliance: float|null, sla_measured: int, satisfaction: float|null, satisfaction_responses: int},
 *     daily: list<array{date: string, created: int, solved: int}>,
 *     breakdowns: array{agent: list<Row>, group: list<Row>, category: list<Row>, channel: list<Row>}
 * }
 */
class TicketReport
{
    public const array DIMENSIONS = ['agent', 'group', 'category', 'channel'];

    private const int CACHE_SECONDS = 300;

    public function __construct(
        public readonly CarbonImmutable $from,
        public readonly CarbonImmutable $to,
        public readonly ?int $groupId = null,
        public readonly ?int $categoryId = null,
        public readonly ?int $assigneeId = null,
        public readonly ?TicketChannel $channel = null,
    ) {}

    /**
     * @return Report
     */
    public function build(): array
    {
        $from = $this->from->startOfDay();
        $to = $this->to->endOfDay();

        $created = 0;
        $solved = 0;
        $slaMeasured = 0;
        $slaKept = 0;
        $firstResponses = [];
        $resolutions = [];
        $createdByDay = [];
        $solvedByDay = [];
        $rows = array_fill_keys(self::DIMENSIONS, []);

        foreach ($this->ticketsInRange($from, $to) as $ticket) {
            $keys = $this->dimensionKeys($ticket);

            if ($ticket->created_at !== null && $ticket->created_at->between($from, $to)) {
                $created++;
                $createdByDay[$this->day($ticket->created_at)] = ($createdByDay[$this->day($ticket->created_at)] ?? 0) + 1;
                $firstResponse = $ticket->first_responded_at !== null ? $this->minutes($ticket->created_at, $ticket->first_responded_at) : null;

                if ($firstResponse !== null) {
                    $firstResponses[] = $firstResponse;
                }

                foreach ($keys as $dimension => $key) {
                    $rows[$dimension][$key] ??= self::emptyRow();
                    $rows[$dimension][$key]['created']++;

                    if ($firstResponse !== null) {
                        $rows[$dimension][$key]['first'][] = $firstResponse;
                    }
                }
            }

            if ($ticket->solved_at !== null && $ticket->solved_at->between($from, $to)) {
                $solved++;
                $solvedByDay[$this->day($ticket->solved_at)] = ($solvedByDay[$this->day($ticket->solved_at)] ?? 0) + 1;
                $resolution = $ticket->created_at !== null ? $this->minutes($ticket->created_at, $ticket->solved_at) : null;

                if ($resolution !== null) {
                    $resolutions[] = $resolution;
                }

                if ($ticket->sla_policy_id !== null) {
                    $slaMeasured++;
                    $slaKept += $ticket->sla_breached_at === null ? 1 : 0;
                }

                foreach ($keys as $dimension => $key) {
                    $rows[$dimension][$key] ??= self::emptyRow();
                    $rows[$dimension][$key]['solved']++;

                    if ($resolution !== null) {
                        $rows[$dimension][$key]['resolution'][] = $resolution;
                    }
                }
            }
        }

        $rated = 0;
        $satisfied = 0;

        foreach ($this->ratingsInRange($from, $to) as $rating) {
            $isSatisfied = $rating->score >= SatisfactionRating::SATISFIED_FROM;
            $rated++;
            $satisfied += $isSatisfied ? 1 : 0;

            foreach ($this->dimensionKeys($rating->ticket) as $dimension => $key) {
                $rows[$dimension][$key] ??= self::emptyRow();
                $rows[$dimension][$key]['rated']++;
                $rows[$dimension][$key]['satisfied'] += $isSatisfied ? 1 : 0;
            }
        }

        return [
            'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'totals' => [
                'created' => $created,
                'solved' => $solved,
                'backlog' => $this->filtered(Ticket::query())->unresolved()->count(),
                'median_first_response_minutes' => self::median($firstResponses),
                'median_resolution_minutes' => self::median($resolutions),
                'sla_compliance' => $slaMeasured > 0 ? round($slaKept / $slaMeasured * 100, 1) : null,
                'sla_measured' => $slaMeasured,
                'satisfaction' => self::percentage($satisfied, $rated),
                'satisfaction_responses' => $rated,
            ],
            'daily' => $this->daily($from, $to, $createdByDay, $solvedByDay),
            'breakdowns' => [
                'agent' => $this->rows($rows['agent'], $this->agentLabels(array_keys($rows['agent']))),
                'group' => $this->rows($rows['group'], $this->groupLabels(array_keys($rows['group']))),
                'category' => $this->rows($rows['category'], $this->categoryLabels(array_keys($rows['category']))),
                'channel' => $this->rows($rows['channel'], $this->channelLabels(array_keys($rows['channel']))),
            ],
        ];
    }

    /**
     * The report, reused for a few minutes per set of filters (it reads every ticket in the range).
     *
     * @return Report
     */
    public function cached(): array
    {
        $key = 'reports:tickets:'.md5((string) json_encode([
            $this->from->toDateString(), $this->to->toDateString(), $this->groupId, $this->categoryId, $this->assigneeId, $this->channel?->value,
        ]));

        return Cache::remember($key, self::CACHE_SECONDS, fn (): array => $this->build());
    }

    /**
     * Tickets created in the range, then tickets solved in it that were created earlier: two
     * queries that can each use an index (one with OR could not), streamed one row at a time.
     *
     * @return iterable<Ticket>
     */
    private function ticketsInRange(CarbonImmutable $from, CarbonImmutable $to): iterable
    {
        $columns = ['id', 'created_at', 'first_responded_at', 'solved_at', 'sla_policy_id', 'sla_breached_at', 'assignee_id', 'group_id', 'category_id', 'channel'];

        yield from $this->filtered(Ticket::query())
            ->whereBetween('created_at', [$from, $to])
            ->select($columns)
            ->cursor();

        yield from $this->filtered(Ticket::query())
            ->whereBetween('solved_at', [$from, $to])
            ->where(fn (Builder $older) => $older->where('created_at', '<', $from)->orWhere('created_at', '>', $to)->orWhereNull('created_at'))
            ->select($columns)
            ->cursor();
    }

    /**
     * Survey answers given in the range, for tickets matching the filters.
     *
     * @return iterable<SatisfactionRating>
     */
    private function ratingsInRange(CarbonImmutable $from, CarbonImmutable $to): iterable
    {
        return SatisfactionRating::query()
            ->whereNotNull('score')
            ->whereBetween('rated_at', [$from, $to])
            ->whereHas('ticket', fn (Builder $tickets) => $this->filtered($tickets))
            ->with('ticket:id,assignee_id,group_id,category_id,channel')
            ->select(['id', 'ticket_id', 'score'])
            ->lazyById(500);
    }

    /**
     * The breakdown row each dimension puts the ticket in.
     *
     * @return array{agent: string, group: string, category: string, channel: string}
     */
    private function dimensionKeys(Ticket $ticket): array
    {
        return [
            'agent' => (string) ($ticket->assignee_id ?? ''),
            'group' => (string) ($ticket->group_id ?? ''),
            'category' => (string) ($ticket->category_id ?? ''),
            'channel' => $ticket->channel->value,
        ];
    }

    /**
     * @return array{created: int, solved: int, first: list<int>, resolution: list<int>, rated: int, satisfied: int}
     */
    private static function emptyRow(): array
    {
        return ['created' => 0, 'solved' => 0, 'first' => [], 'resolution' => [], 'rated' => 0, 'satisfied' => 0];
    }

    private static function percentage(int $part, int $whole): ?float
    {
        return $whole > 0 ? round($part / $whole * 100, 1) : null;
    }

    /**
     * One entry per day of the range, including days without tickets.
     *
     * @param  array<string, int>  $created
     * @param  array<string, int>  $solved
     * @return list<array{date: string, created: int, solved: int}>
     */
    private function daily(CarbonImmutable $from, CarbonImmutable $to, array $created, array $solved): array
    {
        $days = [];

        for ($day = $from; $day->lessThanOrEqualTo($to); $day = $day->addDay()) {
            $date = $day->toDateString();
            $days[] = ['date' => $date, 'created' => $created[$date] ?? 0, 'solved' => $solved[$date] ?? 0];
        }

        return $days;
    }

    /**
     * Middle value of the given numbers (the mean of the two middle ones for an even count).
     *
     * @param  list<int>  $values
     */
    public static function median(array $values): ?int
    {
        if ($values === []) {
            return null;
        }

        sort($values);
        $middle = intdiv(count($values), 2);

        return count($values) % 2 === 1
            ? $values[$middle]
            : (int) round(($values[$middle - 1] + $values[$middle]) / 2);
    }

    /**
     * @param  Builder<Ticket>  $query
     * @return Builder<Ticket>
     */
    private function filtered(Builder $query): Builder
    {
        return $query
            ->when($this->groupId !== null, fn (Builder $byGroup) => $byGroup->where('group_id', $this->groupId))
            ->when($this->assigneeId !== null, fn (Builder $byAgent) => $byAgent->where('assignee_id', $this->assigneeId))
            ->when($this->channel !== null, fn (Builder $byChannel) => $byChannel->where('channel', $this->channel))
            ->when($this->categoryId !== null, fn (Builder $byCategory) => TicketFilters::category($byCategory, $this->categoryId));
    }

    private function day(CarbonInterface $moment): string
    {
        return $moment->setTimezone(config('app.timezone'))->toDateString();
    }

    private function minutes(CarbonInterface $start, CarbonInterface $end): int
    {
        return max(0, (int) round($start->diffInMinutes($end)));
    }

    /**
     * @param  array<int|string, array{created: int, solved: int, first: list<int>, resolution: list<int>, rated: int, satisfied: int}>  $rows
     * @param  array<int|string, string>  $labels
     * @return list<Row>
     */
    private function rows(array $rows, array $labels): array
    {
        $list = [];

        foreach ($rows as $key => $row) {
            $list[] = [
                'key' => (string) $key,
                'label' => $labels[$key] ?? (string) $key,
                'created' => $row['created'],
                'solved' => $row['solved'],
                'median_first_response_minutes' => self::median($row['first']),
                'median_resolution_minutes' => self::median($row['resolution']),
                'satisfaction' => self::percentage($row['satisfied'], $row['rated']),
                'satisfaction_responses' => $row['rated'],
            ];
        }

        usort($list, fn (array $a, array $b): int => [$b['created'], $b['solved'], $a['label']] <=> [$a['created'], $a['solved'], $b['label']]);

        return $list;
    }

    /**
     * @param  list<string|int>  $keys
     * @return array<int|string, string>
     */
    private function agentLabels(array $keys): array
    {
        $labels = ['' => __('Unassigned')];

        foreach (User::query()->whereKey($this->ids($keys))->get(['id', 'name']) as $user) {
            $labels[$user->id] = $user->name;
        }

        return $labels;
    }

    /**
     * @param  list<string|int>  $keys
     * @return array<int|string, string>
     */
    private function groupLabels(array $keys): array
    {
        $labels = ['' => __('No group')];

        foreach (Group::query()->whereKey($this->ids($keys))->get(['id', 'name']) as $group) {
            $labels[$group->id] = $group->name;
        }

        return $labels;
    }

    /**
     * @param  list<string|int>  $keys
     * @return array<int|string, string>
     */
    private function categoryLabels(array $keys): array
    {
        $labels = ['' => __('No category')];

        foreach (TicketCategory::query()->with('parent')->whereKey($this->ids($keys))->get() as $category) {
            $labels[$category->id] = $category->path();
        }

        return $labels;
    }

    /**
     * @param  list<string|int>  $keys
     * @return array<int|string, string>
     */
    private function channelLabels(array $keys): array
    {
        $labels = [];

        foreach ($keys as $key) {
            $labels[$key] = TicketChannel::tryFrom((string) $key)?->label() ?? (string) $key;
        }

        return $labels;
    }

    /**
     * @param  list<string|int>  $keys
     * @return list<int>
     */
    private function ids(array $keys): array
    {
        return array_values(array_map(intval(...), array_filter($keys, fn (string|int $key): bool => $key !== '')));
    }
}
