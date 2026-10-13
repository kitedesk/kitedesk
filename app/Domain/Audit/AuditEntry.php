<?php

namespace App\Domain\Audit;

use App\Domain\Accounts\Models\Group;
use App\Domain\Accounts\Models\Organization;
use App\Domain\Accounts\Models\Role;
use App\Domain\KnowledgeBase\Models\Article;
use App\Domain\KnowledgeBase\Models\Category as HelpCenterCategory;
use App\Domain\KnowledgeBase\Models\Section;
use App\Domain\Mail\Models\EmailTemplate;
use App\Domain\Mail\Models\Mailbox;
use App\Domain\Sla\Models\BusinessSchedule;
use App\Domain\Sla\Models\Holiday;
use App\Domain\Sla\Models\SlaPolicy;
use App\Domain\Tickets\Models\CustomStatus;
use App\Domain\Tickets\Models\RoutingRule;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Tickets\Models\TicketCategory;
use App\Domain\Tickets\Models\TicketField;
use App\Domain\Tickets\Models\TicketForm;
use App\Domain\Webhooks\Models\Webhook;
use App\Domain\Workflows\Models\Workflow;
use App\Models\User;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Activity;

/**
 * One line of the audit log, as the admin center and the CSV export show it: who, did what,
 * to what, from where, and the values that changed.
 */
final class AuditEntry
{
    /**
     * Details kept in the activity's properties that are shown in their own columns.
     *
     * @var list<string>
     */
    private const array OWN_COLUMNS = ['ip', 'user_agent', 'workflow', 'support', 'setting'];

    /**
     * @return array{
     *     id: int,
     *     created_at: string|null,
     *     category: string|null,
     *     event: string|null,
     *     action: string,
     *     actor: array{name: string, user_id: int|null, workflow: string|null, support: string|null},
     *     target: array{type: string|null, name: string|null, url: string|null},
     *     ip: string|null,
     *     user_agent: string|null,
     *     changes: list<array{field: string, old: mixed, new: mixed}>,
     *     details: array<string, mixed>,
     * }
     */
    public static function from(Activity $activity): array
    {
        $properties = $activity->properties?->all() ?? [];
        $old = $activity->attribute_changes?->get('old') ?? [];
        $new = $activity->attribute_changes?->get('attributes') ?? [];

        $changes = [];

        foreach (array_unique([...array_keys($old), ...array_keys($new)]) as $field) {
            $changes[] = ['field' => (string) $field, 'old' => $old[$field] ?? null, 'new' => $new[$field] ?? null];
        }

        $support = $properties['support'] ?? $properties['operator'] ?? null;

        return [
            'id' => $activity->id,
            'created_at' => $activity->created_at?->toIso8601String(),
            'category' => $activity->log_name,
            'event' => $activity->event,
            'action' => self::action($activity->event, $activity->description),
            'actor' => [
                'name' => match (true) {
                    $activity->causer instanceof User => $activity->causer->name,
                    $activity->event === 'support_access' => __('KiteDesk support'),
                    in_array($activity->event, ['login_failed', 'locked_out'], true) => __('Unknown'),
                    default => __('System'),
                },
                'user_id' => $activity->causer instanceof User ? $activity->causer->id : null,
                'workflow' => is_string($properties['workflow'] ?? null) ? $properties['workflow'] : null,
                'support' => is_string($support) ? $support : null,
            ],
            'target' => self::target($activity, $old, $new, $properties),
            'ip' => is_string($properties['ip'] ?? null) ? $properties['ip'] : null,
            'user_agent' => is_string($properties['user_agent'] ?? null) ? $properties['user_agent'] : null,
            'changes' => $changes,
            'details' => array_diff_key($properties, array_flip([...self::OWN_COLUMNS, 'operator', 'operator_email'])),
        ];
    }

    /**
     * What was done, in a few words.
     */
    public static function action(?string $event, string $description = ''): string
    {
        return match ($event) {
            'created' => __('Added'),
            'updated' => __('Changed'),
            'deleted' => __('Removed'),
            'login' => __('Signed in'),
            'logout' => __('Signed out'),
            'login_failed' => __('Failed to sign in'),
            'locked_out' => __('Locked out after too many attempts'),
            'two_factor_failed' => __('Entered a wrong two-factor code'),
            'two_factor_enabled' => __('Turned on two-factor authentication'),
            'two_factor_disabled' => __('Turned off two-factor authentication'),
            'recovery_codes_generated' => __('Generated new recovery codes'),
            'passkey_added' => __('Added a passkey'),
            'passkey_removed' => __('Removed a passkey'),
            'password_changed' => __('Changed their password'),
            'password_reset' => __('Reset their password by email'),
            'password_set' => __('Set a new password'),
            'password_change_required' => __('Asked for a new password'),
            'sessions_revoked' => __('Signed them out everywhere'),
            'two_factor_reset' => __('Reset two-factor authentication'),
            'deactivated' => __('Deactivated the account'),
            'reactivated' => __('Reactivated the account'),
            'permissions_changed' => __('Changed permissions'),
            'settings_updated' => __('Changed settings'),
            'token_created' => __('Created an API token'),
            'token_revoked' => __('Revoked an API token'),
            'ticket_deleted' => __('Deleted a ticket'),
            'rated' => __('Rated a ticket'),
            'support_access' => __('KiteDesk support signed in as them'),
            'secret_requested' => __('Requested a secret'),
            'secret_shared' => __('Shared a secret'),
            'secret_submitted' => __('Submitted a secret'),
            'secret_viewed' => __('Viewed a secret'),
            'secret_revoked' => __('Revoked a secret'),
            'secret_expired' => __('A secret expired'),
            default => $description,
        };
    }

    /**
     * Event names the audit log can be filtered by, with their labels.
     *
     * @return array<string, string>
     */
    public static function actions(): array
    {
        $events = ['created', 'updated', 'deleted', 'login', 'logout', 'login_failed', 'locked_out', 'two_factor_failed', 'two_factor_enabled', 'two_factor_disabled', 'recovery_codes_generated', 'passkey_added', 'passkey_removed', 'password_changed', 'password_reset', 'password_set', 'password_change_required', 'sessions_revoked', 'two_factor_reset', 'deactivated', 'reactivated', 'permissions_changed', 'settings_updated', 'token_created', 'token_revoked', 'ticket_deleted', 'support_access'];

        return collect($events)->mapWithKeys(fn (string $event): array => [$event => self::action($event)])->all();
    }

    /**
     * What it was done to: the kind of thing and its name, and its page when there is one.
     *
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     * @param  array<string, mixed>  $properties
     * @return array{type: string|null, name: string|null, url: string|null}
     */
    private static function target(Activity $activity, array $old, array $new, array $properties): array
    {
        if ($activity->event === 'settings_updated') {
            return ['type' => __('Admin settings'), 'name' => self::settingLabel((string) ($properties['setting'] ?? '')), 'url' => null];
        }

        if ($activity->event === 'ticket_deleted') {
            return ['type' => __('Ticket'), 'name' => trim(($properties['ticket'] ?? '').' · '.($properties['subject'] ?? ''), ' ·'), 'url' => null];
        }

        if ($activity->subject_type === null) {
            // A failed sign-in for an email address no account has.
            $email = $properties['email'] ?? null;

            return is_string($email)
                ? ['type' => __('Account'), 'name' => $email, 'url' => null]
                : ['type' => null, 'name' => null, 'url' => null];
        }

        $subject = $activity->subject;
        $name = $subject instanceof Model ? self::nameOf($subject) : null;

        // Deleted since: its name is still in the recorded values.
        foreach (['name', 'title', 'label', 'subject', 'address'] as $field) {
            $name ??= is_string($new[$field] ?? null) ? $new[$field] : (is_string($old[$field] ?? null) ? $old[$field] : null);
        }

        return [
            'type' => self::typeLabel($activity->subject_type),
            'name' => $name,
            'url' => match (true) {
                $subject instanceof User => route('admin.users.show', $subject),
                $subject instanceof Ticket => route('agent.tickets.show', $subject),
                default => null,
            },
        ];
    }

    private static function nameOf(Model $subject): ?string
    {
        if ($subject instanceof Ticket) {
            return $subject->reference().' · '.$subject->subject;
        }

        foreach (['name', 'title', 'label', 'address', 'event'] as $field) {
            $value = $subject->getAttribute($field);

            if ($value instanceof BackedEnum) {
                $value = $value->value;
            }

            if (is_string($value) && $value !== '') {
                return $subject instanceof Role ? $subject->displayName() : $value;
            }
        }

        return null;
    }

    private static function typeLabel(string $type): string
    {
        return match ($type) {
            User::class => __('User'),
            Ticket::class => __('Ticket'),
            Role::class => __('Role'),
            Group::class => __('Group'),
            Organization::class => __('Organization'),
            Mailbox::class => __('Mailbox'),
            EmailTemplate::class => __('Email template'),
            SlaPolicy::class => __('SLA policy'),
            BusinessSchedule::class => __('Business hours'),
            Holiday::class => __('Holiday'),
            CustomStatus::class => __('Status'),
            RoutingRule::class => __('Routing rule'),
            TicketCategory::class => __('Category'),
            TicketField::class => __('Field'),
            TicketForm::class => __('Form'),
            Webhook::class => __('Webhook'),
            Workflow::class => __('Workflow'),
            Article::class => __('Article'),
            HelpCenterCategory::class => __('Help center category'),
            Section::class => __('Help center section'),
            default => class_basename($type),
        };
    }

    private static function settingLabel(string $setting): string
    {
        return match ($setting) {
            'branding' => __('Branding'),
            'locale' => __('Default language'),
            'ai' => __('AI assistant'),
            'widget' => __('Website widget'),
            'satisfaction' => __('Satisfaction survey'),
            'ticket_numbers' => __('Ticket numbers'),
            'password_policy' => __('Security'),
            default => $setting,
        };
    }
}
