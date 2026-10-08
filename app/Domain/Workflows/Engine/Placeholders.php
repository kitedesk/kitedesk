<?php

namespace App\Domain\Workflows\Engine;

use App\Domain\Mail\Support\TemplateRenderer;
use App\Domain\Support\RichText;
use App\Domain\Tickets\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Fills `{{ placeholders }}` in node settings: the email template variables (`ticket.subject`,
 * `requester.name`...), plus `assignee.name`, `group.name`, `ticket.priority`, `message.body`,
 * `custom.<field>`, `workflow.name`, variables (`vars.<name>`) and the loop item (`item.<key>`,
 * `loop.index`, `loop.count`).
 */
class Placeholders
{
    private const string PATTERN = '/\{\{\s*([a-z0-9_.-]+)\s*\}\}/i';

    /**
     * Plain text (subjects, URLs, condition values, variables).
     */
    public function text(string $template, RunContext $context, ?Ticket $ticket = null, ?User $recipient = null): string
    {
        $resolve = $this->resolver($context, $ticket ?? $context->ticket, $recipient);

        return (string) preg_replace_callback(self::PATTERN, fn (array $match): string => $resolve($match[1]) ?? $match[0], $template);
    }

    /**
     * Rich text written in the editor: values are escaped before they go into the HTML.
     */
    public function html(string $template, RunContext $context, ?Ticket $ticket = null, ?User $recipient = null): string
    {
        $resolve = $this->resolver($context, $ticket ?? $context->ticket, $recipient);

        return (string) preg_replace_callback(self::PATTERN, function (array $match) use ($resolve): string {
            $value = $resolve($match[1]);

            return $value === null ? $match[0] : nl2br(e($value), false);
        }, $template);
    }

    /**
     * @return callable(string): ?string
     */
    private function resolver(RunContext $context, Ticket $ticket, ?User $recipient): callable
    {
        $base = null;

        return function (string $key) use ($context, $ticket, $recipient, &$base): ?string {
            $value = $this->special($key, $context, $ticket);

            if ($value !== false) {
                return $this->stringify($value);
            }

            $base ??= TemplateRenderer::variables($ticket, $recipient ?? $ticket->requester);

            return $base[$key] ?? null;
        };
    }

    /**
     * Value of a workflow-only placeholder, or false when the key isn't one.
     */
    private function special(string $key, RunContext $context, Ticket $ticket): mixed
    {
        return match (true) {
            str_starts_with($key, 'vars.') => data_get($context->vars, Str::after($key, 'vars.')),
            $key === 'item' => $this->scalarItem($context->item()),
            str_starts_with($key, 'item.') => data_get($context->item(), Str::after($key, 'item.')),
            $key === 'loop.index' => $context->iteration(),
            $key === 'loop.count' => $context->loopCount(),
            $key === 'workflow.name' => $context->workflowName,
            str_starts_with($key, 'custom.') => ($ticket->custom_fields ?? [])[Str::after($key, 'custom.')] ?? '',
            $key === 'assignee.name' => $ticket->assignee->name ?? '',
            $key === 'group.name' => $ticket->group->name ?? '',
            $key === 'ticket.priority' => $ticket->priority->label(),
            $key === 'message.body' => ($message = $context->message()) !== null ? RichText::excerpt($message->body, 5000) : '',
            default => false,
        };
    }

    private function scalarItem(mixed $item): mixed
    {
        return is_array($item) && array_key_exists('value', $item) ? $item['value'] : $item;
    }

    private function stringify(mixed $value): string
    {
        return match (true) {
            $value === null => '',
            is_bool($value) => $value ? 'true' : 'false',
            is_scalar($value) => (string) $value,
            default => (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        };
    }
}
