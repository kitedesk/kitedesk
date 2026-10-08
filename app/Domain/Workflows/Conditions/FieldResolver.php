<?php

namespace App\Domain\Workflows\Conditions;

use App\Domain\Sla\SlaCalculator;
use App\Domain\Support\RichText;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Tickets\Models\TicketCategory;
use App\Domain\Workflows\Engine\RunContext;
use BackedEnum;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Reads the value a condition tests.
 *
 * Fields are ticket properties (`status` is the category, `custom_status` the admin-defined
 * status id, `custom_fields.<key>`, ...), loop item properties
 * (`item.<key>`; for a ticket item, any ticket field) or variables (`vars.<name>`).
 * List fields (tags, categories, changed fields) come back as arrays.
 */
class FieldResolver
{
    /**
     * Ticket fields a condition can test, besides `custom_fields.<key>`.
     */
    public const array TICKET_FIELDS = [
        'subject', 'status', 'custom_status', 'priority', 'type', 'channel', 'group', 'assignee', 'category', 'organization',
        'mailbox', 'requester_email', 'requester_name', 'tags', 'collaborators', 'message_body', 'message_author',
        'hours_since_created', 'hours_since_updated', 'hours_since_customer_reply', 'hours_since_agent_reply',
        'in_business_hours', 'sla_breached', 'changed',
    ];

    public function __construct(private SlaCalculator $calculator) {}

    /**
     * Whether a condition may use this field.
     */
    public static function isKnown(string $field): bool
    {
        return in_array($field, self::TICKET_FIELDS, true)
            || (bool) preg_match('/^(custom_fields\.[a-z0-9_]+|vars\.[a-z][a-z0-9_]*(\.[a-z0-9_]+)*|item(\.[a-z0-9_]+)*)$/i', $field);
    }

    public function value(string $field, RunContext $context): mixed
    {
        if ($field === 'item' || str_starts_with($field, 'item.')) {
            return $this->itemValue(Str::after($field, 'item'), $context);
        }

        if (str_starts_with($field, 'vars.')) {
            return data_get($context->vars, Str::after($field, 'vars.'));
        }

        return $this->ticketValue($field, $context->ticket, $context);
    }

    private function itemValue(string $path, RunContext $context): mixed
    {
        $item = $context->item();
        $path = ltrim($path, '.');

        if ($path === '') {
            return is_array($item) && array_key_exists('value', $item) ? $item['value'] : $item;
        }

        if (is_array($item) && ($item['type'] ?? null) === 'ticket' && in_array(Str::before($path, '.'), [...self::TICKET_FIELDS, 'custom_fields'], true)) {
            $ticket = $context->findTicket((int) $item['id']);

            return $ticket !== null ? $this->ticketValue($path, $ticket, $context) : null;
        }

        return data_get($item, $path);
    }

    private function ticketValue(string $field, Ticket $ticket, RunContext $context): mixed
    {
        if (str_starts_with($field, 'custom_fields.')) {
            return ($ticket->custom_fields ?? [])[Str::after($field, 'custom_fields.')] ?? null;
        }

        $isTriggerTicket = $ticket->is($context->ticket);

        return match ($field) {
            'subject' => $ticket->subject,
            'status' => $ticket->status,
            'custom_status' => $ticket->ticket_status_id,
            'priority' => $ticket->priority,
            'type' => $ticket->type,
            'channel' => $ticket->channel,
            'group' => $ticket->group_id,
            'assignee' => $ticket->assignee_id,
            'category' => $this->categoryIds($ticket),
            'organization' => $ticket->organization_id,
            'mailbox' => $ticket->mailbox_id,
            'requester_email' => $ticket->requester->email,
            'requester_name' => $ticket->requester->name,
            'tags' => $ticket->tags()->pluck('name')->all(),
            'collaborators' => $ticket->collaborators()->pluck('email')->all(),
            'message_body' => $this->messageBody($ticket, $context, $isTriggerTicket),
            'message_author' => $this->messageAuthor($ticket, $context, $isTriggerTicket),
            'hours_since_created' => $this->hoursSince($ticket->created_at),
            'hours_since_updated' => $this->hoursSince($ticket->updated_at),
            'hours_since_customer_reply' => $this->hoursSince($ticket->last_customer_reply_at),
            'hours_since_agent_reply' => $this->hoursSince($ticket->last_agent_reply_at),
            'in_business_hours' => $this->inBusinessHours($ticket),
            'sla_breached' => $ticket->sla_breached_at !== null || $ticket->isBreachingSla(),
            'changed' => $isTriggerTicket ? $context->changedFields() : [],
            default => null,
        };
    }

    /**
     * The category and its parent, so "category is Billing" also matches its subcategories.
     *
     * @return list<int>
     */
    private function categoryIds(Ticket $ticket): array
    {
        $category = $ticket->category_id !== null ? TicketCategory::query()->find($ticket->category_id) : null;

        return $category === null ? [] : array_values(array_filter([$category->id, $category->parent_id]));
    }

    private function messageBody(Ticket $ticket, RunContext $context, bool $isTriggerTicket): string
    {
        $message = $isTriggerTicket ? $context->message() : $ticket->messages()->public()->latest('id')->first();

        return $message !== null ? RichText::excerpt($message->body, 5000) : '';
    }

    /**
     * "customer", "agent" or "system" (workflows and other automatic messages).
     */
    private function messageAuthor(Ticket $ticket, RunContext $context, bool $isTriggerTicket): ?string
    {
        $message = $isTriggerTicket ? $context->message() : $ticket->messages()->public()->latest('id')->first();

        if ($message === null) {
            return null;
        }

        $author = $message->author;

        return match (true) {
            $author === null => 'system',
            $author->isCustomer() => 'customer',
            default => 'agent',
        };
    }

    private function hoursSince(?CarbonImmutable $moment): ?float
    {
        return $moment !== null ? round($moment->diffInMinutes(now()) / 60, 2) : null;
    }

    private function inBusinessHours(Ticket $ticket): bool
    {
        $schedule = $ticket->slaPolicy?->businessSchedule;

        if ($schedule === null) {
            return true;
        }

        $now = CarbonImmutable::now();

        return $this->calculator->minutesBetween($now, $now->addMinute(), $schedule) > 0;
    }

    /**
     * Comparable form of a value: enums become their value, booleans "1"/"0", text is lowercased.
     *
     * @return string|list<string>
     */
    public static function normalize(mixed $value): string|array
    {
        if (is_array($value)) {
            return array_values(array_map(fn (mixed $item): string => is_array($item) ? (string) json_encode($item) : self::normalizeScalar($item), $value));
        }

        return self::normalizeScalar($value);
    }

    private static function normalizeScalar(mixed $value): string
    {
        return match (true) {
            $value instanceof BackedEnum => mb_strtolower((string) $value->value),
            is_bool($value) => $value ? '1' : '0',
            is_float($value) => rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.'),
            is_scalar($value) => mb_strtolower(trim((string) $value)),
            default => '',
        };
    }
}
