<?php

namespace App\Domain\Workflows\Enums;

/**
 * What starts a workflow. The trigger node's settings narrow it further (which fields changed,
 * how long a ticket has been idle, who may run it by hand).
 */
enum WorkflowTrigger: string
{
    case TicketCreated = 'ticket_created';
    case TicketUpdated = 'ticket_updated';
    case CustomerReplied = 'customer_replied';
    case AgentReplied = 'agent_replied';
    case NoteAdded = 'note_added';
    case SlaBreached = 'sla_breached';
    case TicketIdle = 'ticket_idle';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::TicketCreated => __('Ticket created'),
            self::TicketUpdated => __('Ticket updated'),
            self::CustomerReplied => __('Customer replied'),
            self::AgentReplied => __('Agent replied'),
            self::NoteAdded => __('Internal note added'),
            self::SlaBreached => __('SLA breached'),
            self::TicketIdle => __('Time-based'),
            self::Manual => __('Run manually'),
        };
    }

    /**
     * Time-based triggers are found by the scheduler (`workflows:scan`) rather than by events.
     */
    public function isTimeBased(): bool
    {
        return $this === self::TicketIdle;
    }
}
