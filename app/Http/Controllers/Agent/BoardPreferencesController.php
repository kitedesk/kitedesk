<?php

namespace App\Http\Controllers\Agent;

use App\Domain\Tickets\Support\BoardPreferences;
use App\Http\Controllers\Controller;
use App\Http\Requests\Agent\UpdateBoardPreferencesRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

/**
 * Saves how the agent likes to see the ticket queue (list or board, lanes, card fields).
 * Changes apply silently, as the agent makes them.
 */
class BoardPreferencesController extends Controller
{
    public function __invoke(UpdateBoardPreferencesRequest $request): RedirectResponse|Response
    {
        BoardPreferences::update($request->user(), $request->validated());

        return $request->wantsJson() ? response()->noContent() : back();
    }
}
