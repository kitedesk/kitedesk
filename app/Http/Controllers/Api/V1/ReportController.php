<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Agent\ReportRequest;
use Illuminate\Http\JsonResponse;

/**
 * @tags Reports
 */
class ReportController extends ApiController
{
    /**
     * Ticket report.
     *
     * The numbers on the Reports page: tickets created and solved, the backlog, median first
     * response and resolution times (calendar minutes), SLA compliance and satisfaction, with a
     * daily series and breakdowns by agent, group, category and channel. Pick the last `range`
     * days (`7`, `30` or `90`, default `30`) or `range=custom` with `from` and `to` (dates, up to
     * a year), and narrow with `group_id`, `category_id` or `assignee_id`. Results are cached for
     * a few minutes.
     */
    public function tickets(ReportRequest $request): JsonResponse
    {
        return response()->json(['data' => $request->report()->cached()]);
    }
}
