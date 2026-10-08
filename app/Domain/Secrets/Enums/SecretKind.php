<?php

namespace App\Domain\Secrets\Enums;

enum SecretKind: string
{
    /** An agent asks the customer for a secret; only that agent can decrypt the answer. */
    case Request = 'request';

    /** An agent sends the customer a secret through a link that carries its key. */
    case Share = 'share';
}
