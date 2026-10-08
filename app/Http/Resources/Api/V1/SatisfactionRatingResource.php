<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Tickets\Models\SatisfactionRating;
use Illuminate\Http\Request;

/**
 * @mixin SatisfactionRating
 */
class SatisfactionRatingResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ticket_id' => $this->ticket_id,
            'requester' => self::person($this->user),
            'score' => $this->score,
            'comment' => $this->comment,
            'sent_at' => self::time($this->sent_at),
            'rated_at' => self::time($this->rated_at),
        ];
    }
}
