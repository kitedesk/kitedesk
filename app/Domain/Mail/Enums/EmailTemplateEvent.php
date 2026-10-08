<?php

namespace App\Domain\Mail\Enums;

/**
 * Emails KiteDesk sends that admins can reword or switch off.
 *
 * Defaults go through __() so a Portuguese installation sends Portuguese emails out of the box.
 */
enum EmailTemplateEvent: string
{
    case TicketReceived = 'ticket_received';
    case AgentReply = 'agent_reply';
    case TicketSolved = 'ticket_solved';
    case AgentNewTicketAlert = 'agent_new_ticket_alert';

    public function label(): string
    {
        return match ($this) {
            self::TicketReceived => __('Request received (auto-reply)'),
            self::AgentReply => __('New reply on a request'),
            self::TicketSolved => __('Request solved'),
            self::AgentNewTicketAlert => __('New ticket alert for agents'),
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::TicketReceived => __('Sent to the requester when a new request arrives by email, the portal or the API.'),
            self::AgentReply => __('Sent to the requester and everyone copied when someone posts a public reply. The reply itself is added below this text.'),
            self::TicketSolved => __('Sent to the requester and everyone copied when the ticket is marked solved.'),
            self::AgentNewTicketAlert => __('Sent to the agents of the ticket\'s group (or all agents when it has no group) when a new ticket arrives.'),
        };
    }

    public function defaultSubject(): string
    {
        return match ($this) {
            self::TicketReceived => __('[{{ticket.number}}] We received your request: {{ticket.subject}}'),
            self::AgentReply => __('[{{ticket.number}}] Re: {{ticket.subject}}'),
            self::TicketSolved => __('[{{ticket.number}}] Solved: {{ticket.subject}}'),
            self::AgentNewTicketAlert => __('[{{ticket.number}}] New ticket: {{ticket.subject}}'),
        };
    }

    public function defaultBody(): string
    {
        return match ($this) {
            self::TicketReceived => __("Hi {{requester.first_name}},\n\nThanks for reaching out. We received your request and will get back to you as soon as possible. Your reference is {{ticket.number}}.\n\nTo add more details, just reply to this email."),
            self::AgentReply => __('{{author.name}} replied to your request:'),
            self::TicketSolved => __("Hi {{requester.first_name}},\n\nWe marked your request {{ticket.number}} as solved. If anything is still not right, just reply to this email and we'll pick it up again."),
            self::AgentNewTicketAlert => __("{{requester.name}} ({{requester.email}}) opened a new ticket:\n\n{{ticket.subject}}"),
        };
    }

    /**
     * Whether this email goes to agents rather than customers.
     */
    public function isForStaff(): bool
    {
        return $this === self::AgentNewTicketAlert;
    }

    /**
     * Placeholders available in this template.
     *
     * @return list<string>
     */
    public function placeholders(): array
    {
        return [
            'ticket.number', 'ticket.id', 'ticket.subject', 'ticket.status', 'ticket.url',
            'requester.name', 'requester.first_name', 'requester.email',
            'recipient.name', 'author.name', 'app.name',
        ];
    }
}
