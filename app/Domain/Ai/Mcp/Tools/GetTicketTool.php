<?php

namespace App\Domain\Ai\Mcp\Tools;

use App\Domain\Ai\Mcp\McpAccess;
use App\Domain\Ai\Support\TicketContext;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_ticket')]
#[Description('Read a ticket: its properties and recent conversation as plain text, including internal notes (labelled; never repeat them to the customer).')]
#[IsReadOnly]
class GetTicketTool extends Tool
{
    public function shouldRegister(Request $request): bool
    {
        return McpAccess::allows($request->user(), 'tickets:read');
    }

    public function handle(Request $request): Response
    {
        $input = $request->validate([
            'ticket' => ['required', 'string', 'max:50'],
            'messages' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $ticket = McpAccess::ticket($user, $input['ticket']);

        if ($ticket === null) {
            return Response::error(__('Ticket not found.'));
        }

        return Response::text(
            TicketContext::for($ticket, $input['messages'] ?? 50)
            ."\n\nURL: ".route('agent.tickets.show', $ticket),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'ticket' => $schema->string()->description('The ticket reference, such as #1042.')->required(),
            'messages' => $schema->integer()->min(1)->max(200)->description('How many recent messages to include. Defaults to 50.'),
        ];
    }
}
