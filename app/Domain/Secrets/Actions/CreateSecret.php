<?php

namespace App\Domain\Secrets\Actions;

use App\Domain\Secrets\Enums\SecretKind;
use App\Domain\Secrets\Models\Secret;
use App\Domain\Secrets\Support\SecretVault;
use App\Domain\Tickets\Models\Ticket;
use App\Models\User;
use Carbon\CarbonImmutable;
use LogicException;

/**
 * Starts a secret on a ticket: a request for the customer to answer, or a secret the agent
 * shares, which is encrypted right away.
 */
class CreateSecret
{
    public function __construct(private SecretVault $vault) {}

    /**
     * @param  string|null  $content  The shared secret; null for requests.
     */
    public function handle(Ticket $ticket, User $agent, SecretKind $kind, string $label, int $maxViews, CarbonImmutable $expiresAt, ?string $content = null): Secret
    {
        if ($kind === SecretKind::Share && ($content === null || $content === '')) {
            throw new LogicException('A shared secret needs its content.');
        }

        $secret = new Secret([
            'kind' => $kind,
            'ticket_id' => $ticket->id,
            'created_by' => $agent->id,
            'label' => $label,
            'max_views' => $maxViews,
            'expires_at' => $expiresAt,
        ]);

        if ($kind === SecretKind::Share) {
            $secret->ciphertext = $this->vault->encrypt((string) $content);
        }

        $secret->save();
        $secret->setRelation('ticket', $ticket);
        $secret->recordActivity($kind === SecretKind::Request ? 'secret_requested' : 'secret_shared', $agent);

        return $secret;
    }
}
