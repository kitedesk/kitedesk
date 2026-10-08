<?php

namespace App\Domain\Secrets\Actions;

use App\Domain\Secrets\Enums\SecretStatus;
use App\Domain\Secrets\Models\Secret;
use App\Domain\Secrets\Support\SecretVault;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Decrypts a secret for someone allowed to read it and counts the view. The content is wiped
 * as soon as the last allowed view is used.
 */
class ViewSecret
{
    public function __construct(private SecretVault $vault) {}

    /**
     * @return array{secret: string, views_left: int}|null Null when it can't be viewed anymore.
     */
    public function handle(Secret $secret, User $viewer): ?array
    {
        $payload = DB::transaction(function () use ($secret): ?array {
            $locked = Secret::query()->lockForUpdate()->findOrFail($secret->id);

            if ($locked->status() !== SecretStatus::Available) {
                return null;
            }

            $payload = [
                'secret' => $this->vault->decrypt((string) $locked->ciphertext),
                'views_left' => $locked->max_views - $locked->views - 1,
            ];

            $locked->increment('views');

            if ($locked->views >= $locked->max_views) {
                $locked->destroyContent();
            }

            $secret->setRawAttributes($locked->getAttributes(), true);

            return $payload;
        });

        if ($payload !== null) {
            $secret->recordActivity('secret_viewed', $viewer, ['views' => $secret->views, 'max_views' => $secret->max_views]);
        }

        return $payload;
    }
}
