<?php

namespace App\Http\Requests\Agent;

use App\Domain\Reports\TicketReport;
use App\Domain\Tickets\Enums\TicketChannel;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Report filters: a preset range (7, 30 or 90 days) or custom dates, plus optional group,
 * category and agent.
 */
class ReportRequest extends FormRequest
{
    public const array RANGES = ['7', '30', '90', 'custom'];

    public function authorize(): bool
    {
        return Gate::allows('viewReports');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'range' => ['nullable', Rule::in(self::RANGES)],
            'from' => ['required_if:range,custom', 'nullable', 'date'],
            'to' => ['required_if:range,custom', 'nullable', 'date', 'after_or_equal:from', 'before_or_equal:today'],
            'group_id' => ['nullable', 'integer', Rule::exists('groups', 'id')],
            'category_id' => ['nullable', 'integer', Rule::exists('ticket_categories', 'id')],
            'assignee_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'channel' => ['nullable', Rule::enum(TicketChannel::class)],
        ];
    }

    public function range(): string
    {
        $range = $this->validated('range');

        return is_string($range) ? $range : '30';
    }

    public function report(): TicketReport
    {
        if ($this->range() === 'custom') {
            $from = CarbonImmutable::parse((string) $this->validated('from'));
            $to = CarbonImmutable::parse((string) $this->validated('to'));

            // Keep reports to at most a year so the daily series stays readable.
            $from = $from->max($to->subYear()->addDay());
        } else {
            $to = Date::today()->toImmutable();
            $from = $to->subDays((int) $this->range() - 1);
        }

        return new TicketReport(
            $from,
            $to,
            groupId: $this->integerOrNull('group_id'),
            categoryId: $this->integerOrNull('category_id'),
            assigneeId: $this->integerOrNull('assignee_id'),
            channel: TicketChannel::tryFrom((string) $this->validated('channel')),
        );
    }

    /**
     * @return array<string, string>
     */
    public function filters(): array
    {
        return array_filter(
            array_map(strval(...), $this->safe()->only(['range', 'from', 'to', 'group_id', 'category_id', 'assignee_id', 'channel'])),
            fn (string $value): bool => $value !== '',
        );
    }

    private function integerOrNull(string $key): ?int
    {
        $value = $this->validated($key);

        return is_numeric($value) ? (int) $value : null;
    }
}
