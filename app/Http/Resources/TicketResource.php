<?php

namespace App\Http\Resources;

use App\Domain\Support\RichText;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Tickets\Models\TicketField;
use App\Domain\Tickets\Support\CustomStatuses;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

/**
 * @mixin Ticket
 */
class TicketResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isStaff = $request->user()?->isStaff() === true;

        return [
            'id' => $this->id,
            'number' => $this->reference(),
            'subject' => $this->subject,
            'status' => $this->status->value,
            // Admin-defined statuses are internal; customers see the category.
            'custom_status' => $this->when($isStaff, fn () => CustomStatuses::find($this->ticket_status_id)?->toSummary()),
            'priority' => $this->priority->value,
            'type' => $this->type?->value,
            'channel' => $this->channel->value,
            'requester' => $this->whenLoaded('requester', fn () => (new UserSummaryResource($this->requester))->resolve($request)),
            'assignee' => $this->whenLoaded('assignee', fn () => $this->assignee ? (new UserSummaryResource($this->assignee))->resolve($request) : null),
            'collaborators' => $this->whenLoaded('collaborators', fn () => $this->collaborators
                ->map(fn ($collaborator) => (new UserSummaryResource($collaborator))->resolve($request))
                ->values()),
            'group' => $this->whenLoaded('group', fn () => $this->group ? ['id' => $this->group->id, 'name' => $this->group->name] : null),
            'category' => $this->whenLoaded('category', fn () => $this->category ? [
                'id' => $this->category->id,
                'parent_id' => $this->category->parent_id,
                'name' => $this->category->name,
                'path' => $this->category->path(),
            ] : null),
            'form_id' => $this->ticket_form_id,
            'organization' => $this->when($isStaff, fn () => $this->whenLoaded('organization', fn () => $this->organization ? ['id' => $this->organization->id, 'name' => $this->organization->name] : null)),
            'tags' => $this->when($isStaff, fn () => $this->whenLoaded('tags', fn () => $this->tags->pluck('name')->all())),
            'custom_fields' => (object) ($isStaff ? $this->custom_fields ?? [] : Arr::only($this->custom_fields ?? [], TicketField::customerVisibleKeys())),
            'sla' => $this->when($isStaff, fn () => [
                'policy' => $this->whenLoaded('slaPolicy', fn () => $this->slaPolicy?->name),
                'first_response_due_at' => $this->first_response_due_at?->toIso8601String(),
                'next_reply_due_at' => $this->next_reply_due_at?->toIso8601String(),
                'resolution_due_at' => $this->resolution_due_at?->toIso8601String(),
                'resolution_paused' => $this->resolution_remaining_minutes !== null,
                'breached' => $this->isBreachingSla(),
            ]),
            'latest_message' => $this->when($isStaff, fn () => $this->whenLoaded('latestMessage', fn () => $this->latestMessage ? [
                'excerpt' => RichText::excerpt($this->latestMessage->body, 120),
                'is_internal' => $this->latestMessage->is_internal,
            ] : null)),
            'messages_count' => $this->whenCounted('messages'),
            'first_responded_at' => $this->first_responded_at?->toIso8601String(),
            'solved_at' => $this->solved_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
