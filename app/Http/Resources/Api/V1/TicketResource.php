<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Tickets\Models\Ticket;
use App\Domain\Tickets\Support\CustomStatuses;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * A ticket as seen by staff. Also the `ticket` object of webhook payloads.
 *
 * @mixin Ticket
 */
class TicketResource extends ApiResource
{
    /**
     * Relations every ticket response includes.
     *
     * @var list<string>
     */
    public const array RELATIONS = ['requester', 'assignee', 'group', 'category.parent', 'organization', 'tags', 'slaPolicy', 'collaborators'];

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $this->resource->loadMissing(self::RELATIONS);

        return [
            'id' => $this->id,
            /** The reference shown to people, e.g. `#1042`. */
            'number' => $this->reference(),
            'subject' => $this->subject,
            'status' => $this->status->value,
            'custom_status' => CustomStatuses::find($this->ticket_status_id)?->toSummary(),
            'priority' => $this->priority->value,
            'type' => $this->type?->value,
            'channel' => $this->channel->value,
            'requester' => self::person($this->requester),
            'assignee' => self::person($this->assignee),
            'group' => self::named($this->group),
            'organization' => self::named($this->organization),
            'category' => $this->category === null ? null : [
                'id' => $this->category->id,
                'parent_id' => $this->category->parent_id,
                'name' => $this->category->name,
                'path' => $this->category->path(),
            ],
            'form_id' => $this->ticket_form_id,
            'collaborators' => $this->collaborators->map(fn (User $user): ?array => self::person($user))->values()->all(),
            /** @var list<string> */
            'tags' => $this->tags->pluck('name')->values()->all(),
            'custom_fields' => (object) ($this->custom_fields ?? []),
            'sla' => [
                'policy' => self::named($this->slaPolicy),
                'first_response_due_at' => self::time($this->first_response_due_at),
                'next_reply_due_at' => self::time($this->next_reply_due_at),
                'resolution_due_at' => self::time($this->resolution_due_at),
                'resolution_paused' => $this->resolution_remaining_minutes !== null,
                'breached' => $this->isBreachingSla(),
            ],
            'first_responded_at' => self::time($this->first_responded_at),
            'solved_at' => self::time($this->solved_at),
            'created_at' => self::time($this->created_at),
            'updated_at' => self::time($this->updated_at),
        ];
    }
}
