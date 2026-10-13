<?php

namespace App\Domain\Accounts\Models;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A password someone used before (its hash), so the password policy can refuse it again.
 *
 * @property int $id
 * @property int $user_id
 * @property string $password
 * @property CarbonImmutable|null $created_at
 */
#[Fillable(['password'])]
#[Hidden(['password'])]
class PasswordHistory extends Model
{
    public const null UPDATED_AT = null;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
