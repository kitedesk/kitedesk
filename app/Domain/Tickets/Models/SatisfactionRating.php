<?php

namespace App\Domain\Tickets\Models;

use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\SatisfactionRatingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\URL;

/**
 * The customer's 1–5 star rating of how a ticket was handled. Created when the survey is
 * emailed (score still null) or when the customer rates from the portal first.
 *
 * @property int $id
 * @property int $ticket_id
 * @property int|null $user_id
 * @property int|null $score
 * @property string|null $comment
 * @property CarbonImmutable|null $sent_at
 * @property CarbonImmutable|null $rated_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Ticket $ticket
 * @property-read User|null $user
 */
#[Fillable(['ticket_id', 'user_id', 'score', 'comment', 'sent_at', 'rated_at'])]
#[UseFactory(SatisfactionRatingFactory::class)]
class SatisfactionRating extends Model
{
    /** @use HasFactory<SatisfactionRatingFactory> */
    use HasFactory;

    /**
     * Scores of 4 and 5 count as satisfied.
     */
    public const int SATISFIED_FROM = 4;

    /**
     * How long the links in the survey email work.
     */
    public const int LINK_DAYS = 30;

    /**
     * @return BelongsTo<Ticket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The signed link behind one of the survey email's stars; it opens the rating page without a login.
     */
    public function urlFor(int $score): string
    {
        return URL::temporarySignedRoute('guest.satisfaction.show', now()->addDays(self::LINK_DAYS), [
            'rating' => $this->id,
            'score' => $score,
        ]);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'sent_at' => 'datetime',
            'rated_at' => 'datetime',
        ];
    }
}
