<?php

namespace App\Http\Controllers\Agent;

use App\Domain\Accounts\Models\Group;
use App\Domain\Reports\TicketReport;
use App\Domain\Support\EnumOptions;
use App\Domain\Tickets\Enums\TicketChannel;
use App\Domain\Tickets\Support\SatisfactionSurvey;
use App\Domain\Tickets\Support\TicketCatalog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Agent\ReportRequest;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(ReportRequest $request): Response
    {
        return Inertia::render('agent/reports/index', [
            'report' => $request->report()->cached(),
            'filters' => ['range' => $request->range(), ...$request->filters()],
            'satisfactionEnabled' => SatisfactionSurvey::current()->enabled,
            'options' => [
                'groups' => Group::query()->orderBy('name')->get(['id', 'name']),
                'categories' => TicketCatalog::categories(),
                'agents' => User::query()->assignable()->orderBy('name')->get(['id', 'name']),
                'channels' => EnumOptions::for(TicketChannel::class),
            ],
        ]);
    }

    /**
     * The same report as CSV: one block of totals, the daily series and each breakdown.
     */
    public function export(ReportRequest $request): StreamedResponse
    {
        Gate::authorize('exportReports');

        $report = $request->report()->cached();
        $filename = "kitedesk-report-{$report['range']['from']}-{$report['range']['to']}.csv";

        return response()->streamDownload(function () use ($report): void {
            $out = fopen('php://output', 'w');

            if ($out === false) {
                return;
            }

            fputcsv($out, [__('Metric'), __('Value')]);

            foreach ($report['totals'] as $metric => $value) {
                fputcsv($out, [$metric, $value ?? '']);
            }

            fputcsv($out, []);
            fputcsv($out, [__('Date'), __('Created'), __('Solved')]);

            foreach ($report['daily'] as $day) {
                fputcsv($out, [$day['date'], $day['created'], $day['solved']]);
            }

            foreach (TicketReport::DIMENSIONS as $dimension) {
                fputcsv($out, []);
                fputcsv($out, [$dimension, __('Created'), __('Solved'), 'median_first_response_minutes', 'median_resolution_minutes', 'satisfaction', 'satisfaction_responses']);

                foreach ($report['breakdowns'][$dimension] as $row) {
                    fputcsv($out, [$row['label'], $row['created'], $row['solved'], $row['median_first_response_minutes'] ?? '', $row['median_resolution_minutes'] ?? '', $row['satisfaction'] ?? '', $row['satisfaction_responses']]);
                }
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
