<?php

namespace App\Domain\Workflows\Engine;

use App\Domain\Support\RichText;
use App\Domain\Tickets\Enums\TicketStatus;
use App\Domain\Tickets\Models\Tag;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Tickets\Models\TicketMessage;
use App\Domain\Tickets\Support\TicketFilters;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * The lists a "For each" node can loop over. Items are plain arrays, so they can be logged and
 * used in placeholders (`{{item.email}}`); ticket items carry `type: "ticket"` and an `id`, so
 * actions can apply to them and conditions can test any of their fields.
 */
class LoopCollections
{
    public const int MAX_ITEMS = 100;

    public const array COLLECTIONS = [
        'tags', 'collaborators', 'messages', 'customer_messages', 'attachments', 'linked_tickets',
        'requester_tickets', 'organization_tickets', 'find_tickets', 'variable',
    ];

    /**
     * @param  array<string, mixed>  $data
     * @return list<mixed>
     */
    public function items(array $data, RunContext $context): array
    {
        $ticket = $context->ticket;
        $limit = max(1, min((int) ($data['limit'] ?? self::MAX_ITEMS), self::MAX_ITEMS));
        /** @var list<string> $statuses */
        $statuses = is_array($data['statuses'] ?? null) ? $data['statuses'] : [];

        $items = match ($data['collection'] ?? null) {
            'tags' => $ticket->tags()->orderBy('name')->limit($limit)->get()->map(fn (Tag $tag): array => ['name' => $tag->name])->all(),
            'collaborators' => $ticket->collaborators()->orderBy('email')->limit($limit)->get()->map(fn (User $user): array => $this->person($user))->all(),
            'messages', 'customer_messages' => $this->messages($ticket, $data['collection'] === 'customer_messages', $limit),
            'attachments' => $this->attachments($ticket, $limit),
            'linked_tickets' => $this->tickets($ticket->linkedTickets()->getQuery(), $statuses, $limit),
            'requester_tickets' => $this->tickets(Ticket::query()->where('requester_id', $ticket->requester_id)->whereKeyNot($ticket->id), $statuses, $limit),
            'organization_tickets' => $ticket->organization_id === null ? [] : $this->tickets(Ticket::query()->where('organization_id', $ticket->organization_id)->whereKeyNot($ticket->id), $statuses, $limit),
            'find_tickets' => $this->tickets(TicketFilters::apply(Ticket::query(), $this->filters($data, $context), new User), $statuses, $limit),
            'variable' => $this->variable($data, $context, $limit),
            default => [],
        };

        return array_values($items);
    }

    /**
     * @return array{type: 'ticket', id: int, number: string, subject: string, status: string, priority: string, requester_email: string, url: string}
     */
    public static function ticketItem(Ticket $ticket): array
    {
        return [
            'type' => 'ticket',
            'id' => $ticket->id,
            'number' => $ticket->reference(),
            'subject' => $ticket->subject,
            'status' => $ticket->status->value,
            'priority' => $ticket->priority->value,
            'requester_email' => $ticket->requester->email,
            'url' => route('agent.tickets.show', $ticket),
        ];
    }

    /**
     * @param  Builder<Ticket>  $query
     * @param  list<string>  $statuses
     * @return list<array<string, mixed>>
     */
    private function tickets(Builder $query, array $statuses, int $limit): array
    {
        return array_values($query
            ->when($statuses !== [], fn (Builder $query) => $query->whereIn('tickets.status', $statuses))
            ->with('requester')
            ->orderBy('tickets.id')
            ->limit($limit)
            ->get()
            ->map(fn (Ticket $ticket): array => self::ticketItem($ticket))
            ->all());
    }

    /**
     * Search filters, with placeholders filled (`{{requester.email}}`).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function filters(array $data, RunContext $context): array
    {
        $filters = is_array($data['filters'] ?? null) ? $data['filters'] : [];
        $placeholders = app(Placeholders::class);

        return collect($filters)
            ->only(array_diff(TicketFilters::KEYS, ['assignee_id']))
            ->map(fn (mixed $value): mixed => is_string($value) ? $placeholders->text($value, $context) : $value)
            ->put('assignee_id', $filters['assignee_id'] ?? null)
            ->reject(fn (mixed $value): bool => $value === TicketFilters::ME)
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function messages(Ticket $ticket, bool $fromCustomer, int $limit): array
    {
        return array_values($ticket->messages()
            ->with('author')
            ->when($fromCustomer, fn (Builder $query) => $query->public()->whereHas('author', fn (Builder $author) => $author->customers()))
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->map(fn (TicketMessage $message): array => [
                'id' => $message->id,
                'body' => RichText::excerpt($message->body, 5000),
                'author_name' => $message->author !== null ? $message->author->name : '',
                'author_email' => $message->author !== null ? $message->author->email : '',
                'is_internal' => $message->is_internal,
                'created_at' => $message->created_at?->toIso8601String(),
            ])
            ->all());
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function attachments(Ticket $ticket, int $limit): array
    {
        return array_values(Media::query()
            ->where('model_type', (new TicketMessage)->getMorphClass())
            ->whereIn('model_id', $ticket->messages()->select('id'))
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->map(fn (Media $media): array => [
                'name' => $media->file_name,
                'mime_type' => $media->mime_type,
                'size' => $media->size,
            ])
            ->all());
    }

    /**
     * Items of a list variable (or a list inside one, like `response.body.items`).
     *
     * @param  array<string, mixed>  $data
     * @return list<mixed>
     */
    private function variable(array $data, RunContext $context, int $limit): array
    {
        $value = data_get($context->vars, (string) ($data['variable'] ?? ''));

        if (! is_array($value) || ! array_is_list($value)) {
            return [];
        }

        return array_map(
            fn (mixed $item): mixed => is_array($item) ? $item : ['value' => $item],
            array_slice($value, 0, $limit),
        );
    }

    /**
     * @return array{id: int, name: string, email: string}
     */
    private function person(User $user): array
    {
        return ['id' => $user->id, 'name' => $user->name, 'email' => $user->email];
    }

    /**
     * Statuses a ticket collection can be limited to.
     *
     * @return list<string>
     */
    public static function statuses(): array
    {
        return array_map(fn (TicketStatus $status): string => $status->value, TicketStatus::cases());
    }
}
