<?php

namespace App\Domain\Mail\Models;

use App\Domain\Entitlements\Enums\Feature;
use App\Domain\Entitlements\Support\PlanLimits;
use App\Domain\Mail\Enums\EmailTemplateEvent;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * An admin's wording for one of the emails in EmailTemplateEvent. Events without a row use the defaults.
 *
 * @property int $id
 * @property EmailTemplateEvent $event
 * @property string $subject
 * @property string $body
 * @property bool $is_active
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['event', 'subject', 'body', 'is_active'])]
class EmailTemplate extends Model
{
    /**
     * The saved template for the event, or an unsaved one holding the defaults. While the plan
     * leaves custom wording out, only whether the email is sent is taken from the saved one.
     */
    public static function for(EmailTemplateEvent $event): self
    {
        $saved = self::query()->where('event', $event->value)->first();

        if ($saved !== null && PlanLimits::allows(Feature::CustomEmailTemplates)) {
            return $saved;
        }

        return new self([
            'event' => $event,
            'subject' => $event->defaultSubject(),
            'body' => $event->defaultBody(),
            'is_active' => $saved->is_active ?? true,
        ]);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event' => EmailTemplateEvent::class,
            'is_active' => 'boolean',
        ];
    }
}
