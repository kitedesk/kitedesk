<?php

namespace App\Domain\Tickets\Actions;

use App\Domain\Sla\SlaTracker;
use App\Domain\Tickets\Enums\TicketPriority;
use App\Domain\Tickets\Enums\TicketStatus;
use App\Domain\Tickets\Enums\TicketType;
use App\Domain\Tickets\Events\TicketUpdated;
use App\Domain\Tickets\Models\Tag;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Tickets\Models\TicketCategory;
use App\Domain\Tickets\Models\TicketForm;
use App\Domain\Tickets\Routing\AutoAssigner;
use App\Domain\Tickets\Routing\TicketRouter;
use App\Domain\Tickets\Support\CustomStatuses;
use App\Models\User;
use BackedEnum;
use Illuminate\Support\Facades\DB;

/**
 * Changes ticket properties and keeps status timestamps and SLA clocks consistent.
 *
 * Changing the category switches the ticket's form and re-runs routing, unless the
 * same change sets the group explicitly. Moving an unassigned ticket to another group
 * lets that group's assignment mode pick an agent.
 */
class UpdateTicket
{
    public function __construct(private SlaTracker $sla, private TicketRouter $router, private AutoAssigner $assigner) {}

    /**
     * @param  array{
     *     subject?: string,
     *     status?: TicketStatus|string,
     *     ticket_status_id?: int,
     *     priority?: TicketPriority|string,
     *     type?: TicketType|string|null,
     *     assignee_id?: int|null,
     *     group_id?: int|null,
     *     category_id?: int|null,
     *     tags?: list<string>,
     *     collaborator_ids?: list<int>,
     *     custom_fields?: array<string, mixed>,
     * }  $attributes
     * @param  bool  $notifyAssignee  False when the caller notifies a new assignee itself.
     */
    public function handle(Ticket $ticket, array $attributes, ?User $actor = null, bool $notifyAssignee = true): Ticket
    {
        return DB::transaction(function () use ($ticket, $attributes, $actor, $notifyAssignee): Ticket {
            $originalStatus = $ticket->status;
            $originalTags = $ticket->tags()->pluck('name')->sort()->values()->all();

            $ticket->fill(collect($attributes)->except(['tags', 'custom_fields', 'collaborator_ids'])->all());
            CustomStatuses::applyChosenStatus($ticket);

            if (array_key_exists('custom_fields', $attributes)) {
                $ticket->custom_fields = array_merge($ticket->custom_fields ?? [], $attributes['custom_fields']);
            }

            $routedTags = $ticket->isDirty('category_id')
                ? $this->applyCategory($ticket, routeGroup: ! array_key_exists('group_id', $attributes), setPriority: ! array_key_exists('priority', $attributes))
                : [];

            if ($ticket->isDirty('group_id') && ! array_key_exists('assignee_id', $attributes) && $ticket->status->isUnresolved()) {
                $this->assigner->assign($ticket);
            }

            if ($ticket->isDirty('assignee_id') && $ticket->assignee_id !== null && $ticket->status === TicketStatus::New) {
                $ticket->status = TicketStatus::Open;
            }

            $this->applyStatusTimestamps($ticket, $originalStatus);

            if ($ticket->isDirty(['priority', 'group_id'])) {
                $this->sla->apply($ticket);
            } elseif ($ticket->status !== $originalStatus) {
                $this->sla->recordStatusChange($ticket, $originalStatus, $ticket->status);
            }

            CustomStatuses::matchCategory($ticket);
            $changes = $this->describeChanges($ticket);
            $ticket->save();

            if (array_key_exists('tags', $attributes) || $routedTags !== []) {
                $tags = array_key_exists('tags', $attributes) ? $attributes['tags'] : $originalTags;
                $ticket->tags()->sync(Tag::idsFor(array_values(array_unique([...$tags, ...$routedTags]))));
                $newTags = $ticket->tags()->pluck('name')->sort()->values()->all();

                if ($newTags !== $originalTags) {
                    $changes['tags'] = ['from' => $originalTags, 'to' => $newTags];
                }
            }

            if (array_key_exists('collaborator_ids', $attributes)) {
                $changes += $this->syncCollaborators($ticket, $attributes['collaborator_ids']);
            }

            if ($changes !== []) {
                TicketUpdated::dispatch($ticket, $changes, $actor, $notifyAssignee);
            }

            return $ticket;
        });
    }

    /**
     * Replace the people copied on the ticket (the requester is never a collaborator).
     *
     * @param  list<int>  $userIds
     * @return array<string, array{from: list<string>, to: list<string>}>
     */
    private function syncCollaborators(Ticket $ticket, array $userIds): array
    {
        $before = $this->collaboratorEmails($ticket);

        $ticket->collaborators()->sync(array_values(array_diff($userIds, [$ticket->requester_id])));

        $after = $this->collaboratorEmails($ticket);

        return $before === $after ? [] : ['collaborators' => ['from' => $before, 'to' => $after]];
    }

    /**
     * @return list<string>
     */
    private function collaboratorEmails(Ticket $ticket): array
    {
        return array_values(array_map(strval(...), $ticket->collaborators()->orderBy('email')->pluck('email')->all()));
    }

    /**
     * Switch to the new category's form and, when asked, route the ticket again.
     *
     * @return list<string> Tags added by the matching routing rule.
     */
    private function applyCategory(Ticket $ticket, bool $routeGroup, bool $setPriority): array
    {
        $category = $ticket->category_id !== null
            ? TicketCategory::query()->with('parent', 'form', 'parent.form')->find($ticket->category_id)
            : null;

        $ticket->setRelation('category', $category);
        $ticket->ticket_form_id = ($category !== null ? $category->resolveForm() : TicketForm::default())?->id;

        return $routeGroup ? $this->router->route($ticket, $setPriority) : [];
    }

    private function applyStatusTimestamps(Ticket $ticket, TicketStatus $originalStatus): void
    {
        if ($ticket->status === $originalStatus) {
            return;
        }

        if ($ticket->status === TicketStatus::Solved) {
            $ticket->solved_at = now();
        }

        if ($ticket->status === TicketStatus::Closed) {
            $ticket->solved_at ??= now();
            $ticket->closed_at = now();
        }

        if ($ticket->status->isUnresolved()) {
            $ticket->solved_at = null;
            $ticket->closed_at = null;
        }
    }

    /**
     * @return array<string, array{from: mixed, to: mixed}>
     */
    private function describeChanges(Ticket $ticket): array
    {
        $tracked = ['subject', 'status', 'ticket_status_id', 'priority', 'type', 'assignee_id', 'group_id', 'category_id', 'custom_fields'];

        return collect($ticket->getDirty())
            ->only($tracked)
            ->mapWithKeys(fn (mixed $value, string $key): array => [$key => [
                'from' => $this->scalar($ticket->getOriginal($key)),
                'to' => $this->scalar($ticket->getAttribute($key)),
            ]])
            ->all();
    }

    private function scalar(mixed $value): mixed
    {
        return $value instanceof BackedEnum ? $value->value : $value;
    }
}
