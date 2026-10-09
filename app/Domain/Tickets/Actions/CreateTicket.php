<?php

namespace App\Domain\Tickets\Actions;

use App\Domain\Accounts\Models\Organization;
use App\Domain\Sla\SlaTracker;
use App\Domain\Support\RichText;
use App\Domain\Tickets\Enums\TicketChannel;
use App\Domain\Tickets\Enums\TicketPriority;
use App\Domain\Tickets\Enums\TicketStatus;
use App\Domain\Tickets\Enums\TicketType;
use App\Domain\Tickets\Events\TicketCreated;
use App\Domain\Tickets\Models\Tag;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Tickets\Models\TicketCategory;
use App\Domain\Tickets\Models\TicketForm;
use App\Domain\Tickets\Routing\AutoAssigner;
use App\Domain\Tickets\Routing\TicketRouter;
use App\Domain\Tickets\Support\TicketNumberGenerator;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class CreateTicket
{
    public function __construct(private SlaTracker $sla, private TicketRouter $router, private TicketNumberGenerator $numbers, private AutoAssigner $assigner) {}

    /**
     * @param  array{
     *     subject: string,
     *     body: string,
     *     priority?: TicketPriority|string|null,
     *     type?: TicketType|string|null,
     *     assignee_id?: int|null,
     *     group_id?: int|null,
     *     category_id?: int|null,
     *     tags?: list<string>,
     *     collaborator_ids?: list<int>,
     *     custom_fields?: array<string, mixed>,
     *     attachments?: list<UploadedFile>,
     * }  $attributes
     * @param  User|null  $author  Who wrote the first message, when different from the requester (e.g. an agent on the phone).
     *
     * The category picks the ticket's form. When no group is given, routing rules choose one,
     * and the group's assignment mode may then pick an agent.
     *
     * The number and the assignee are settled before the main transaction, so their row locks
     * (sequence counter, round-robin group) are released before the slower writes and file
     * copies. A failed creation can leave a gap in the sequence.
     */
    public function handle(User $requester, array $attributes, TicketChannel $channel, ?User $author = null): Ticket
    {
        $assigneeId = $attributes['assignee_id'] ?? null;
        $category = isset($attributes['category_id']) ? TicketCategory::query()->with('parent', 'form', 'parent.form')->find($attributes['category_id']) : null;

        $ticket = new Ticket([
            'subject' => $attributes['subject'],
            'status' => $assigneeId === null ? TicketStatus::New : TicketStatus::Open,
            'priority' => $attributes['priority'] ?? TicketPriority::Normal,
            'type' => $attributes['type'] ?? null,
            'channel' => $channel,
            'requester_id' => $requester->id,
            'assignee_id' => $assigneeId,
            'group_id' => $attributes['group_id'] ?? null,
            'category_id' => $category?->id,
            'ticket_form_id' => ($category !== null ? $category->resolveForm() : TicketForm::default())?->id,
            'organization_id' => $requester->organization_id ?? Organization::forEmail($requester->email)?->id,
            'custom_fields' => $attributes['custom_fields'] ?? null,
        ]);
        $ticket->created_at = now();
        $ticket->last_customer_reply_at = ($author ?? $requester)->isStaff() && $channel !== TicketChannel::Internal ? null : $ticket->created_at;
        $ticket->number = $this->numbers->next($ticket->created_at);
        $ticket->setRelation('category', $category);
        $ticket->setRelation('requester', $requester);

        $routedTags = $ticket->group_id === null
            ? $this->router->route($ticket, setPriority: ! isset($attributes['priority']))
            : [];

        $this->assigner->assign($ticket);
        $this->sla->apply($ticket);

        return DB::transaction(function () use ($ticket, $requester, $attributes, $channel, $author, $routedTags): Ticket {
            $ticket->save();

            $message = $ticket->messages()->create([
                'author_id' => ($author ?? $requester)->id,
                'body' => RichText::sanitize($attributes['body']),
                'is_internal' => false,
                'channel' => $channel,
            ]);

            foreach ($attributes['attachments'] ?? [] as $attachment) {
                $message->addMedia($attachment)->toMediaCollection('attachments');
            }

            $collaboratorIds = array_values(array_diff($attributes['collaborator_ids'] ?? [], [$requester->id]));

            if ($collaboratorIds !== []) {
                $ticket->collaborators()->sync($collaboratorIds);
            }

            $tags = array_values(array_unique([...$attributes['tags'] ?? [], ...$routedTags]));

            if ($tags !== []) {
                $ticket->tags()->sync(Tag::idsFor($tags));
            }

            TicketCreated::dispatch($ticket);

            return $ticket;
        });
    }
}
