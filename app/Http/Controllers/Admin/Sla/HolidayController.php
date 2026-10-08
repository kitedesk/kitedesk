<?php

namespace App\Http\Controllers\Admin\Sla;

use App\Domain\Sla\Models\BusinessSchedule;
use App\Domain\Sla\Models\Holiday;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Sla\StoreHolidayRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class HolidayController extends Controller
{
    public function store(StoreHolidayRequest $request, BusinessSchedule $schedule): RedirectResponse
    {
        $schedule->holidays()->create([
            'name' => $request->string('name')->toString(),
            'date' => $request->string('date')->toString(),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Holiday added.')]);

        return back();
    }

    public function destroy(BusinessSchedule $schedule, Holiday $holiday): RedirectResponse
    {
        $holiday->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Holiday removed.')]);

        return back();
    }
}
