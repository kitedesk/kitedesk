<?php

namespace App\Domain\Ai\Mcp\Tools;

use App\Domain\Ai\Mcp\McpAccess;
use App\Domain\Support\RichText;
use App\Domain\Tickets\Actions\AddMessage;
use App\Domain\Tickets\Enums\TicketChannel;
use App\Domain\Tickets\Enums\TicketStatus;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('reply_to_ticket')]
#[Description('Add a message to a ticket as you. A public reply is emailed to the customer; set internal to true for a note only staff can see. Plain text paragraphs (blank lines) or simple HTML.')]
class ReplyToTicketTool extends Tool
{
    public function shouldRegister(Request $request): bool
    {
        return McpAccess::allows($request->user(), 'tickets:write');
    }

    public function handle(Request $request, AddMessage $addMessage): Response
    {
        $input = $request->validate([
            'ticket' => ['required', 'string', 'max:50'],
            'body' => ['required', 'string', 'max:65000'],
            'internal' => ['nullable', 'boolean'],
            'status_after' => ['nullable', Rule::enum(TicketStatus::class)],
        ]);

        /** @var User $user */
        $user = $request->user();
        $ticket = McpAccess::ticket($user, $input['ticket']);
        $internal = (bool) ($input['internal'] ?? false);

        if ($ticket === null) {
            return Response::error(__('Ticket not found.'));
        }

        if (Gate::forUser($user)->denies($internal ? 'addInternalNote' : 'reply', $ticket)) {
            return Response::error(__('You are not allowed to do that on this ticket.'));
        }

        $body = RichText::fromPlainText($input['body']);

        if (RichText::isBlank(RichText::sanitize($body))) {
            return Response::error(__('The message is empty.'));
        }

        $addMessage->handle(
            $ticket,
            $user,
            $body,
            isInternal: $internal,
            channel: TicketChannel::Api,
            statusAfter: isset($input['status_after']) ? TicketStatus::from($input['status_after']) : null,
            metadata: ['via' => 'mcp'],
        );

        return Response::text($internal
            ? __('Internal note added to :ticket.', ['ticket' => $ticket->reference()])
            : __('Reply sent on :ticket.', ['ticket' => $ticket->reference()]));
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'ticket' => $schema->string()->description('The ticket reference, such as #1042.')->required(),
            'body' => $schema->string()->description('The message. Separate paragraphs with a blank line.')->required(),
            'internal' => $schema->boolean()->description('True for an internal note. Defaults to false (a public reply).'),
            'status_after' => $schema->string()->enum(array_column(TicketStatus::cases(), 'value'))->description('Optionally change the status at the same time, e.g. pending while waiting for the customer.'),
        ];
    }
}
