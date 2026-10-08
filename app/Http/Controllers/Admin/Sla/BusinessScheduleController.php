<?php

namespace App\Http\Controllers\Admin\Sla;

use App\Domain\Sla\Models\BusinessSchedule;
use App\Domain\Sla\Models\Holiday;
use App\Domain\Sla\Models\SlaPolicy;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Sla\SaveBusinessScheduleRequest;
use DateTimeZone;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class BusinessScheduleController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/sla/schedules/index', [
            'schedules' => BusinessSchedule::query()
                ->withCount('holidays')
                ->orderBy('name')
                ->get()
                ->map(fn (BusinessSchedule $schedule): array => [
                    'id' => $schedule->id,
                    'name' => $schedule->name,
                    'timezone' => $schedule->timezone,
                    'hours' => $this->hours($schedule),
                    'holidays_count' => $schedule->holidays_count,
                    'policies_count' => SlaPolicy::query()->where('business_schedule_id', $schedule->id)->count(),
                ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/sla/schedules/form', [
            'schedule' => null,
            'timezones' => DateTimeZone::listIdentifiers(),
            'defaultTimezone' => config('app.timezone'),
        ]);
    }

    public function store(SaveBusinessScheduleRequest $request): RedirectResponse
    {
        $schedule = BusinessSchedule::query()->create([
            'name' => $request->string('name')->toString(),
            'timezone' => $request->string('timezone')->toString(),
            'hours' => $request->hours(),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Business schedule created. You can now add holidays.')]);

        return to_route('admin.business-schedules.edit', $schedule);
    }

    public function edit(BusinessSchedule $schedule): Response
    {
        return Inertia::render('admin/sla/schedules/form', [
            'schedule' => [
                'id' => $schedule->id,
                'name' => $schedule->name,
                'timezone' => $schedule->timezone,
                'hours' => $this->hours($schedule),
                'holidays' => $schedule->holidays()
                    ->orderBy('date')
                    ->get()
                    ->map(fn (Holiday $holiday): array => [
                        'id' => $holiday->id,
                        'name' => $holiday->name,
                        'date' => $holiday->date->toDateString(),
                    ]),
            ],
            'timezones' => DateTimeZone::listIdentifiers(),
            'defaultTimezone' => config('app.timezone'),
        ]);
    }

    public function update(SaveBusinessScheduleRequest $request, BusinessSchedule $schedule): RedirectResponse
    {
        $schedule->update([
            'name' => $request->string('name')->toString(),
            'timezone' => $request->string('timezone')->toString(),
            'hours' => $request->hours(),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Business schedule updated.')]);

        return to_route('admin.business-schedules.edit', $schedule);
    }

    /**
     * Policies using the schedule fall back to 24/7 calendar time (FK is nullOnDelete).
     */
    public function destroy(BusinessSchedule $schedule): RedirectResponse
    {
        $schedule->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Business schedule deleted.')]);

        return to_route('admin.business-schedules.index');
    }

    /**
     * Weekly hours keyed by ISO weekday 1..7 with every day present (serializes as a JSON object).
     *
     * @return array<int, list<array{start: string, end: string}>>
     */
    private function hours(BusinessSchedule $schedule): array
    {
        $hours = [];

        foreach (range(1, 7) as $day) {
            $hours[$day] = $schedule->hours[$day] ?? [];
        }

        return $hours;
    }
}
