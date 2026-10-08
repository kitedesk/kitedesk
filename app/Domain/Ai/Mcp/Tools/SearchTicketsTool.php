<?php

namespace App\Domain\Ai\Mcp\Tools;

use App\Domain\Ai\Mcp\McpAccess;
use App\Domain\Tickets\Enums\TicketPriority;
use App\Domain\Tickets\Enums\TicketStatus;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Tickets\Support\CustomStatuses;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('search_tickets')]
#[Description('Search the tickets you can see, newest activity first. Matches the subject or ticket number; filter by status, priority, group or tickets assigned to you. Returns ticket references for get_ticket.')]
#[IsReadOnly]
class SearchTicketsTool extends Tool
{
    public function shouldRegister(Request $request): bool
    {
        return McpAccess::allows($request->user(), 'tickets:read');
    }

    public function handle(Request $request): Response
    {
        $input = $request->validate([
            'query' => ['nullable', 'string', 'max:200'],
            'status' => ['nullable', Rule::enum(TicketStatus::class)],
            'priority' => ['nullable', Rule::enum(TicketPriority::class)],
            'group_id' => ['nullable', 'integer'],
            'assigned_to_me' => ['nullable', 'boolean'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $term = trim((string) ($input['query'] ?? ''));
        $number = ltrim($term, '#');

        $tickets = Ticket::query()
            ->visibleTo($user)
            ->with(['requester', 'assignee', 'group'])
            ->when($term !== '', fn (Builder $query) => $query->where(fn (Builder $match) => $match
                ->where('subject', 'like', "%{$term}%")
                ->orWhere('number', 'like', "{$number}%")))
            ->when($input['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($input['priority'] ?? null, fn (Builder $query, string $priority) => $query->where('priority', $priority))
            ->when($input['group_id'] ?? null, fn (Builder $query, int $group) => $query->where('group_id', $group))
            ->when($input['assigned_to_me'] ?? false, fn (Builder $query) => $query->where('assignee_id', $user->id))
            ->latest('updated_at')
            ->limit($input['limit'] ?? 20)
            ->get();

        return Response::json([
            'tickets' => $tickets->map(fn (Ticket $ticket): array => [
                'reference' => $ticket->reference(),
                'subject' => $ticket->subject,
                'status' => CustomStatuses::find($ticket->ticket_status_id)->name ?? $ticket->status->label(),
                'status_category' => $ticket->status->value,
                'priority' => $ticket->priority->value,
                'requester' => $ticket->requester->name,
                'assignee' => $ticket->assignee?->name,
                'group' => $ticket->group?->name,
                'updated_at' => $ticket->updated_at?->toIso8601String(),
                'url' => route('agent.tickets.show', $ticket),
            ])->all(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->description('Words from the subject, or a ticket number such as #1042.'),
            'status' => $schema->string()->enum(array_column(TicketStatus::cases(), 'value'))->description('Status category.'),
            'priority' => $schema->string()->enum(array_column(TicketPriority::cases(), 'value')),
            'group_id' => $schema->integer(),
            'assigned_to_me' => $schema->boolean()->description('Only tickets assigned to you.'),
            'limit' => $schema->integer()->min(1)->max(50)->description('Defaults to 20.'),
        ];
    }
}
