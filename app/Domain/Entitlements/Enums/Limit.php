<?php

namespace App\Domain\Entitlements\Enums;

use App\Domain\Mail\Models\Mailbox;
use App\Domain\Tickets\Models\TicketField;
use App\Domain\Tickets\Models\TicketForm;
use App\Domain\Workflows\Models\Workflow;
use App\Models\User;

/**
 * Countable things a plan can cap.
 */
enum Limit: string
{
    case AgentSeats = 'agent_seats';
    case Mailboxes = 'mailboxes';
    case ActiveWorkflows = 'active_workflows';
    case CustomFields = 'custom_fields';
    case TicketForms = 'ticket_forms';

    /**
     * How many the installation has now.
     */
    public function usage(): int
    {
        return match ($this) {
            self::AgentSeats => User::query()->staff()->active()->count(),
            self::Mailboxes => Mailbox::query()->count(),
            self::ActiveWorkflows => Workflow::query()->where('is_active', true)->count(),
            self::CustomFields => TicketField::query()->count(),
            self::TicketForms => TicketForm::query()->count(),
        };
    }

    /**
     * Shown when the limit is reached.
     */
    public function message(int $limit): string
    {
        return match ($this) {
            self::AgentSeats => trans_choice('Your plan includes :count team member.|Your plan includes :count team members.', $limit),
            self::Mailboxes => trans_choice('Your plan includes :count mailbox.|Your plan includes :count mailboxes.', $limit),
            self::ActiveWorkflows => trans_choice('Your plan includes :count active workflow.|Your plan includes :count active workflows.', $limit),
            self::CustomFields => trans_choice('Your plan includes :count custom field.|Your plan includes :count custom fields.', $limit),
            self::TicketForms => trans_choice('Your plan includes :count ticket form.|Your plan includes :count ticket forms.', $limit),
        };
    }
}
