<?php

namespace App\Http\Controllers\Agent;

use App\Domain\Jira\Actions\CreateIssue;
use App\Domain\Jira\Actions\LinkIssue;
use App\Domain\Jira\Actions\SyncIssue;
use App\Domain\Jira\Actions\UnlinkIssue;
use App\Domain\Jira\Exceptions\JiraException;
use App\Domain\Jira\Models\JiraIssueLink;
use App\Domain\Jira\Support\JiraClient;
use App\Domain\Jira\Support\JiraHealth;
use App\Domain\Jira\Support\JiraSettings;
use App\Domain\Tickets\Models\Ticket;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * The Jira issues linked to a ticket, for the panel on the ticket page. Changes answer with
 * the panel again, so it can simply show what it gets. Jira's own errors come back as a
 * validation error on `jira`.
 *
 * Every action takes the ticket, even when it doesn't use it: that is what makes the route
 * bind it, so the `can:view,ticket` middleware checks the real ticket.
 */
class JiraIssueController extends Controller
{
    public function index(Request $request, Ticket $ticket): JsonResponse
    {
        return $this->respond($request, $ticket);
    }

    /**
     * Issues matching a key, a Jira URL or words from their text, newest first.
     */
    public function search(Request $request, Ticket $ticket): JsonResponse
    {
        $query = trim($request->string('q')->toString());

        if (mb_strlen($query) < 2) {
            return response()->json(['issues' => []]);
        }

        $key = self::issueKey($query);
        $words = self::searchWords($query);

        if ($key === null && $words === '') {
            return response()->json(['issues' => []]);
        }

        $jql = $key !== null
            ? 'key = "'.$key.'"'
            : 'text ~ "'.$words.'*" ORDER BY updated DESC';

        $issues = $this->jira(fn () => JiraClient::make()->search($jql, 10));

        return response()->json(['issues' => array_map(fn (array $issue): array => [
            'key' => $issue['key'],
            'summary' => $issue['summary'],
            'status' => $issue['status_name'],
            'status_category' => $issue['status_category']?->value,
        ], $issues)]);
    }

    public function projects(Ticket $ticket): JsonResponse
    {
        return response()->json(['projects' => $this->jira(fn () => JiraClient::make()->projects())]);
    }

    public function issueTypes(Request $request, Ticket $ticket): JsonResponse
    {
        $project = $request->validate(['project' => ['required', 'string', 'max:50']])['project'];

        return response()->json(['issue_types' => $this->jira(fn () => JiraClient::make()->issueTypes($project))]);
    }

    /**
     * Creates an issue from the ticket and links it.
     */
    public function store(Request $request, Ticket $ticket, CreateIssue $createIssue): JsonResponse
    {
        $input = $request->validate([
            'project' => ['required', 'string', 'max:50'],
            'issue_type' => ['required', 'string', 'max:50'],
            'summary' => ['required', 'string', 'max:255'],
        ]);

        $this->jira(fn () => $createIssue->handle($ticket, $input['project'], $input['issue_type'], $input['summary'], $request->user()));

        return $this->respond($request, $ticket);
    }

    /**
     * Links an existing issue, given its key or its Jira URL.
     */
    public function link(Request $request, Ticket $ticket, LinkIssue $linkIssue): JsonResponse
    {
        $request->validate(['issue' => ['required', 'string', 'max:500']]);
        $key = self::issueKey($request->string('issue')->toString());

        if ($key === null) {
            throw ValidationException::withMessages(['issue' => __('Enter an issue key, such as ENG-12, or its Jira link.')]);
        }

        $this->jira(fn () => $linkIssue->handle($ticket, $key, $request->user()));

        return $this->respond($request, $ticket);
    }

    /**
     * The statuses the issue can move to from where it is, as its Jira workflow allows.
     */
    public function transitions(Ticket $ticket, JiraIssueLink $jiraIssueLink): JsonResponse
    {
        abort_unless($jiraIssueLink->ticket_id === $ticket->id, 404);

        $transitions = $this->jira(fn () => JiraClient::make()->transitions($jiraIssueLink->issue_key));

        return response()->json(['transitions' => array_map(fn (array $transition): array => [
            'id' => $transition['id'],
            'name' => $transition['name'],
            'to' => $transition['to']['name'],
            'to_category' => $transition['to']['category']?->value,
        ], $transitions)]);
    }

    /**
     * Moves the issue in Jira, then brings the change to the ticket right away (note and
     * status rules), as Jira's webhook would.
     */
    public function transition(Request $request, Ticket $ticket, JiraIssueLink $jiraIssueLink, SyncIssue $syncIssue): JsonResponse
    {
        abort_unless($jiraIssueLink->ticket_id === $ticket->id, 404);
        $input = $request->validate(['transition' => ['required', 'string', 'max:50']]);

        $this->jira(function () use ($jiraIssueLink, $input, $syncIssue, $request): void {
            $client = JiraClient::make();
            $client->transition($jiraIssueLink->issue_key, $input['transition']);
            $syncIssue->handle($jiraIssueLink, $client->issue($jiraIssueLink->issue_key), $request->user());
        });

        return $this->respond($request, $ticket);
    }

    public function destroy(Request $request, Ticket $ticket, JiraIssueLink $jiraIssueLink, UnlinkIssue $unlinkIssue): JsonResponse
    {
        abort_unless($jiraIssueLink->ticket_id === $ticket->id, 404);

        $unlinkIssue->handle($jiraIssueLink, $request->user());

        return $this->respond($request, $ticket);
    }

    /**
     * The issue key in "ENG-12", "eng-12" or a Jira link such as https://acme.atlassian.net/browse/ENG-12.
     */
    public static function issueKey(string $value): ?string
    {
        $value = trim($value);

        if (preg_match('~/browse/([A-Za-z][A-Za-z0-9_]*-\d+)~', $value, $match) === 1
            || preg_match('~[?&]selectedIssue=([A-Za-z][A-Za-z0-9_]*-\d+)~', $value, $match) === 1
            || preg_match('~^([A-Za-z][A-Za-z0-9_]*-\d+)$~', $value, $match) === 1) {
            return strtoupper($match[1]);
        }

        return null;
    }

    /**
     * The words of a text search, without the characters Jira's text search reads as syntax
     * (`+ - & | ! ( ) { } [ ] ^ ~ * ? : \ / " '`), which would make it reject the query.
     */
    public static function searchWords(string $query): string
    {
        $words = preg_replace('/[+\-&|!(){}\[\]^~*?:\\\\\/"\']+/u', ' ', mb_substr($query, 0, 100)) ?? '';

        return trim((string) preg_replace('/\s+/u', ' ', $words));
    }

    private function respond(Request $request, Ticket $ticket): JsonResponse
    {
        $settings = JiraSettings::current();

        return response()->json([
            'connected' => $settings->isConnected(),
            'error' => JiraHealth::error()['message'] ?? null,
            'can_manage' => $request->user()->hasPermission('jira.manage_links'),
            'defaults' => ['project' => $settings->defaultProject, 'issue_type' => $settings->defaultIssueType],
            'issues' => JiraIssueLink::query()
                ->where('ticket_id', $ticket->id)
                ->orderBy('id')
                ->get()
                ->map(fn (JiraIssueLink $link): array => $link->toPanelArray())
                ->values(),
        ]);
    }

    /**
     * Runs a Jira call, turning Jira's refusal into a message for the panel.
     *
     * @template TResult
     *
     * @param  callable(): TResult  $call
     * @return TResult
     */
    private function jira(callable $call): mixed
    {
        try {
            return $call();
        } catch (JiraException $exception) {
            throw ValidationException::withMessages(['jira' => $exception->status === 404
                ? __('Jira has no such issue, or this account can\'t see it.')
                : $exception->getMessage()]);
        }
    }
}
