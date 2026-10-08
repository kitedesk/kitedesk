<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Tickets\Support\SatisfactionSurvey;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveSatisfactionSurveyRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The customer satisfaction survey sent after tickets are solved.
 */
class SatisfactionSurveyController extends Controller
{
    public function edit(): Response
    {
        $survey = SatisfactionSurvey::current();

        return Inertia::render('admin/satisfaction/edit', [
            'settings' => [
                'enabled' => $survey->enabled,
                'delay_hours' => $survey->delayHours,
            ],
            'enabledSince' => $survey->enabledSince?->toIso8601String(),
            'delays' => SatisfactionSurvey::DELAYS,
        ]);
    }

    public function update(SaveSatisfactionSurveyRequest $request): RedirectResponse
    {
        SatisfactionSurvey::current()
            ->update($request->boolean('enabled'), $request->integer('delay_hours'))
            ->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => $request->boolean('enabled')
            ? __('Satisfaction survey settings saved.')
            : __('Satisfaction survey turned off.')]);

        return back();
    }
}
