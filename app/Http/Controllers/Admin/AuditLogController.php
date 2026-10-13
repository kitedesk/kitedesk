<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Audit\AuditEntry;
use App\Domain\Audit\AuditLog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AuditLogRequest;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Who signed in, and who changed users, roles, settings and tickets.
 */
class AuditLogController extends Controller
{
    public function index(AuditLogRequest $request): Response
    {
        $entries = $request->activities()
            ->cursorPaginate(50)
            ->withQueryString()
            ->through(fn (Activity $activity): array => AuditEntry::from($activity));

        return Inertia::render('admin/audit-log/index', [
            'entries' => $entries,
            'filters' => $request->filters(),
            'categories' => AuditLog::categories(),
            'actions' => AuditEntry::actions(),
            'people' => User::query()->staff()->orderBy('name')->get(['id', 'name']),
            'selectedUser' => $request->filters()['user'] !== null ? User::query()->whereKey($request->filters()['user'])->first(['id', 'name']) : null,
            'retentionDays' => (int) config('activitylog.clean_after_days'),
        ]);
    }

    public function export(AuditLogRequest $request): StreamedResponse
    {
        $query = $request->activities();

        return response()->streamDownload(function () use ($query): void {
            $out = fopen('php://output', 'w');

            if ($out === false) {
                return;
            }

            fputcsv($out, [__('Date'), __('Category'), __('Person'), __('Action'), __('Type'), __('Target'), __('Changes'), __('Details'), __('IP address'), __('Browser')]);

            $categories = AuditLog::categories();

            foreach ($query->lazyByIdDesc(500, 'id') as $activity) {
                $entry = AuditEntry::from($activity);
                $actor = $entry['actor']['name'].($entry['actor']['support'] !== null ? ' ('.__('KiteDesk support').': '.$entry['actor']['support'].')' : '').($entry['actor']['workflow'] !== null ? ' ('.__('Workflow').': '.$entry['actor']['workflow'].')' : '');

                fputcsv($out, [
                    $entry['created_at'],
                    $categories[$entry['category']] ?? $entry['category'],
                    $actor,
                    $entry['action'],
                    $entry['target']['type'],
                    $entry['target']['name'],
                    collect($entry['changes'])->map(fn (array $change): string => "{$change['field']}: ".json_encode($change['old']).' → '.json_encode($change['new']))->implode("\n"),
                    $entry['details'] !== [] ? json_encode($entry['details']) : '',
                    $entry['ip'],
                    $entry['user_agent'],
                ]);
            }

            fclose($out);
        }, 'kitedesk-audit-log-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }
}
