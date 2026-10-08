<?php

namespace App\Http\Resources;

use App\Domain\Secrets\Enums\SecretStatus;
use App\Domain\Secrets\Models\Secret;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A secret's card on a ticket. Never includes the content: it is decrypted on request by
 * the reveal endpoints, which check who is asking and count the view.
 *
 * @mixin Secret
 */
class SecretResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $isStaff = $user?->isStaff() === true;
        $status = $this->status();

        return [
            'token' => $this->token,
            'kind' => $this->kind->value,
            'label' => $this->label,
            'status' => $status->value,
            'status_label' => $status->label(),
            'views' => $this->views,
            'max_views' => $this->max_views,
            'expires_at' => $this->expires_at->toIso8601String(),
            // The page the customer opens; it asks them to sign in.
            'url' => $this->url(),
            'creator' => $this->when($isStaff, fn (): ?array => $this->creator ? ['id' => $this->creator->id, 'name' => $this->creator->name] : null),
            'can_reveal' => $this->when($isStaff, fn (): bool => $status === SecretStatus::Available && $user->can('view', $this->resource)),
            'can_revoke' => $this->when($isStaff, fn (): bool => ! $status->isFinal() && $user->can('revoke', $this->resource)),
        ];
    }
}
