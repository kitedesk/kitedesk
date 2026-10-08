<?php

namespace App\Domain\Api\Actions;

use App\Domain\Accounts\Enums\TicketAccess;
use App\Domain\Api\Enums\ApiAbility;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\NewAccessToken;

/**
 * Issues a REST API token. Staff create tokens for themselves; someone else's token can only be
 * created by a person who already has everything that owner can do, so a token is never a way
 * to borrow more access. The abilities are capped by the owner's permissions.
 */
class CreateApiToken
{
    /**
     * Lifetimes to pick from; every token expires.
     *
     * @var list<int>
     */
    public const array EXPIRY_DAYS = [30, 90, 365];

    /**
     * @param  list<string>  $abilities
     */
    public function handle(User $creator, User $owner, string $name, array $abilities, int $expiresInDays): NewAccessToken
    {
        if (! self::mayIssueFor($creator, $owner)) {
            throw ValidationException::withMessages(['user_id' => __('You can only create tokens for people who cannot do more than you.')]);
        }

        $abilities = array_values(array_unique($abilities));

        foreach ($abilities as $index => $ability) {
            if (ApiAbility::tryFrom($ability)?->allowedFor($owner) !== true) {
                throw ValidationException::withMessages(["abilities.{$index}" => __("The token owner's role does not allow this.")]);
            }
        }

        $token = $owner->createToken($name, $abilities, now()->addDays($expiresInDays));
        $token->accessToken->forceFill(['created_by_id' => $creator->id])->save();

        return $token;
    }

    /**
     * Whether the creator may issue a token owned by this person: themselves, or staff whose
     * permissions and ticket access don't exceed the creator's.
     */
    public static function mayIssueFor(User $creator, User $owner): bool
    {
        if (! $owner->isStaff() || $owner->isDeactivated()) {
            return false;
        }

        if ($creator->is($owner)) {
            return true;
        }

        $ownerPermissions = $owner->getAllPermissions()->pluck('name');
        $creatorPermissions = $creator->getAllPermissions()->pluck('name');

        return $ownerPermissions->diff($creatorPermissions)->isEmpty()
            && self::accessRank($owner->ticketAccess()) <= self::accessRank($creator->ticketAccess());
    }

    private static function accessRank(TicketAccess $access): int
    {
        return match ($access) {
            TicketAccess::Assigned => 0,
            TicketAccess::Groups => 1,
            TicketAccess::All => 2,
        };
    }
}
