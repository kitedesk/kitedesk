<?php

namespace App\Domain\Secrets\Actions;

use App\Domain\Secrets\Enums\SecretStatus;
use App\Domain\Secrets\Models\Secret;
use App\Domain\Secrets\Notifications\SecretSubmitted;
use App\Domain\Secrets\Support\SecretVault;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Stores the customer's answer to a secret request, encrypted. A request can be answered once.
 */
class SubmitSecret
{
    public function __construct(private SecretVault $vault) {}

    /**
     * @return bool Whether it was accepted; false when the request was already answered or is no longer open.
     */
    public function handle(Secret $secret, User $customer, string $content): bool
    {
        $accepted = DB::transaction(function () use ($secret, $customer, $content): bool {
            $locked = Secret::query()->lockForUpdate()->findOrFail($secret->id);

            if (! $locked->isRequest() || $locked->status() !== SecretStatus::Pending) {
                return false;
            }

            $locked->forceFill([
                'ciphertext' => $this->vault->encrypt($content),
                'submitted_by' => $customer->id,
                'submitted_at' => now(),
            ])->save();

            $secret->setRawAttributes($locked->getAttributes(), true);

            return true;
        });

        if ($accepted) {
            $secret->recordActivity('secret_submitted', $customer);
            $secret->creator?->notify(new SecretSubmitted($secret));
        }

        return $accepted;
    }
}
