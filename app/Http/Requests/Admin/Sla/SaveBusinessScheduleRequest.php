<?php

namespace App\Http\Requests\Admin\Sla;

use DateTimeZone;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveBusinessScheduleRequest extends FormRequest
{
    private const TIME = '/^([01]\d|2[0-3]):[0-5]\d$/';

    private const END_TIME = '/^(([01]\d|2[0-3]):[0-5]\d|24:00)$/';

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'timezone' => ['required', 'string', Rule::in(DateTimeZone::listIdentifiers())],
            'hours' => ['present', 'array'],
            'hours.*' => ['array', 'max:6'],
            'hours.*.*.start' => ['required', 'string', 'regex:'.self::TIME],
            'hours.*.*.end' => ['required', 'string', 'regex:'.self::END_TIME],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                foreach ((array) $this->input('hours', []) as $day => $intervals) {
                    if (! in_array((int) $day, range(1, 7), true) || (string) (int) $day !== (string) $day) {
                        $validator->errors()->add("hours.{$day}", __('Days must be numbered 1 (Monday) to 7 (Sunday).'));

                        continue;
                    }

                    $previousEnd = null;
                    $sorted = collect((array) $intervals)->filter(fn ($interval): bool => is_array($interval))->sortBy('start');

                    foreach ($sorted as $index => $interval) {
                        $start = (string) ($interval['start'] ?? '');
                        $end = (string) ($interval['end'] ?? '');

                        if ($start !== '' && $end !== '' && $start >= $end) {
                            $validator->errors()->add("hours.{$day}.{$index}.end", __('The end time must be after the start time.'));
                        }

                        if ($previousEnd !== null && $start < $previousEnd) {
                            $validator->errors()->add("hours.{$day}.{$index}.start", __('Intervals on the same day must not overlap.'));
                        }

                        $previousEnd = $end;
                    }
                }
            },
        ];
    }

    /**
     * Weekly hours keyed by ISO weekday with intervals sorted by start time.
     *
     * @return array<int, list<array{start: string, end: string}>>
     */
    public function hours(): array
    {
        $hours = [];

        foreach ((array) $this->validated('hours', []) as $day => $intervals) {
            $list = array_values(collect((array) $intervals)
                ->map(fn ($interval): array => ['start' => (string) $interval['start'], 'end' => (string) $interval['end']])
                ->sortBy('start')
                ->all());

            if ($list !== []) {
                $hours[(int) $day] = $list;
            }
        }

        ksort($hours);

        return $hours;
    }
}
