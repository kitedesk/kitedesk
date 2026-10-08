<?php

namespace App\Domain\Secrets\Enums;

enum SecretStatus: string
{
    /** A request the customer hasn't answered yet. */
    case Pending = 'pending';

    case Available = 'available';

    /** Viewed as many times as allowed. */
    case UsedUp = 'used_up';

    case Expired = 'expired';

    case Revoked = 'revoked';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Waiting for the customer'),
            self::Available => __('Available'),
            self::UsedUp => __('Used up'),
            self::Expired => __('Expired'),
            self::Revoked => __('Revoked'),
        };
    }

    /**
     * Whether the encrypted content is gone for good.
     */
    public function isFinal(): bool
    {
        return ! in_array($this, [self::Pending, self::Available], true);
    }
}
