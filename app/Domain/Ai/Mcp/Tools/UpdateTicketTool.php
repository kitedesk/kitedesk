<?php

namespace App\Domain\Ai\Mcp\Tools;

use App\Domain\Ai\Mcp\McpAccess;
use App\Domain\Tickets\Actions\UpdateTicket;
use App\Domain\Tickets\Enums\TicketPriority;
use App\Domain\Tickets\Enums\TicketStatus;
use App\Domain\Tickets\Support\CustomStatuses;
use App\Models\User;
use App\Rules\AssignableAgent;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Name('update_ticket')]
#[Description('Change a ticket\'s status, priority, assignee or group. Only the fields you pass change; pass null for assignee_id or group_id to clear them.')]
#[IsIdempotent]
class UpdateTicketTool extends Tool
{
    public function shouldRegister(Request $request): bool
    {
        return McpAccess::allows($request->user(), 'tickets:write');
    }

    public function handle(Request $request, UpdateTicket $updateTicket): Response
    {
        $input = $request->validate([
            'ticket' => ['required', 'string', 'max:50'],
            'status' => ['sometimes', Rule::enum(TicketStatus::class)],
            'priority' => ['sometimes', Rule::enum(TicketPriority::class)],
            'assignee_id' => ['sometimes', 'nullable', 'integer', new AssignableAgent],
            'group_id' => ['sometimes', 'nullable', 'integer', Rule::exists('groups', 'id')],
        ]);

        /** @var User $user */
        $user = $request->user();
        $ticket = McpAccess::ticket($user, $input['ticket']);

        if ($ticket === null) {
            return Response::error(__('Ticket not found.'));
        }

        if (Gate::forUser($user)->denies('update', $ticket)) {
            return Response::error(__('You are not allowed to do that on this ticket.'));
        }

        $changes = Arr::except($input, 'ticket');

        if ($changes === []) {
            return Response::error(__('Nothing to change.'));
        }

        $ticket = $updateTicket->handle($ticket, $changes, $user)->load(['assignee', 'group']);

        return Response::json([
            'reference' => $ticket->reference(),
            'status' => CustomStatuses::find($ticket->ticket_status_id)->name ?? $ticket->status->label(),
            'priority' => $ticket->priority->value,
            'assignee' => $ticket->assignee?->name,
            'group' => $ticket->group?->name,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'ticket' => $schema->string()->description('The ticket reference, such as #1042.')->required(),
            'status' => $schema->string()->enum(array_column(TicketStatus::cases(), 'value')),
            'priority' => $schema->string()->enum(array_column(TicketPriority::cases(), 'value')),
            'assignee_id' => $schema->integer()->nullable()->description('A staff member\'s user id.'),
            'group_id' => $schema->integer()->nullable(),
        ];
    }
}
