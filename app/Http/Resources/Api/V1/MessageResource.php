<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Tickets\Models\TicketMessage;
use Illuminate\Http\Request;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * A reply or internal note on a ticket. Also the `message` object of webhook payloads.
 *
 * @mixin TicketMessage
 */
class MessageResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $this->resource->loadMissing(['author', 'media']);

        return [
            'id' => $this->id,
            'ticket_id' => $this->ticket_id,
            /** Sanitized HTML. */
            'body' => $this->body,
            /** Internal notes are only visible to staff. */
            'is_internal' => $this->is_internal,
            'channel' => $this->channel->value,
            'author' => $this->author === null ? null : [...self::person($this->author), 'type' => $this->author->type->value],
            'attachments' => $this->getMedia('attachments')->map(fn (Media $media): array => [
                'id' => $media->id,
                'name' => $media->file_name,
                'size' => $media->size,
                'mime_type' => $media->mime_type,
                /** Download with the same token. */
                'url' => route('api.v1.attachments.show', $media),
            ])->values()->all(),
            'created_at' => self::time($this->created_at),
        ];
    }
}
