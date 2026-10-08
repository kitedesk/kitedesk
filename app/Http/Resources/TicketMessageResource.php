<?php

namespace App\Http\Resources;

use App\Domain\Tickets\Models\TicketMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @mixin TicketMessage
 */
class TicketMessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'body' => $this->body,
            'is_internal' => $this->is_internal,
            'channel' => $this->channel->value,
            // Messages written by a workflow name it, for staff only.
            'workflow' => $request->user()?->isStaff() ? ($this->metadata['workflow']['name'] ?? null) : null,
            // Handover notes name the agent or group the ticket was forwarded to (internal, staff only).
            'forwarded_to' => $request->user()?->isStaff() ? ($this->metadata['forwarded_to']['name'] ?? null) : null,
            // Drafted with the AI assistant, or sent from an AI client over MCP (staff only).
            'ai_assisted' => $request->user()?->isStaff() ? (bool) ($this->metadata['ai_assisted'] ?? false) : false,
            'via_mcp' => $request->user()?->isStaff() ? ($this->metadata['via'] ?? null) === 'mcp' : false,
            'author' => $this->whenLoaded('author', fn () => $this->author ? (new UserSummaryResource($this->author))->resolve($request) : null),
            'attachments' => $this->whenLoaded('media', fn () => $this->getMedia('attachments')->map(fn (Media $media): array => [
                'id' => $media->id,
                'name' => $media->file_name,
                'size' => $media->size,
                'mime_type' => $media->mime_type,
                'url' => route('attachments.show', $media),
            ])->values()),
            'secrets' => $this->whenLoaded('secrets', fn () => SecretResource::collection($this->secrets)->resolve($request)),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
