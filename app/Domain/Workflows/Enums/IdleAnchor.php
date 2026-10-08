<?php

namespace App\Domain\Workflows\Enums;

/**
 * The moment a time-based workflow counts from.
 */
enum IdleAnchor: string
{
    case Created = 'created';
    case Updated = 'updated';
    case CustomerReply = 'customer_reply';
    case AgentReply = 'agent_reply';

    public function label(): string
    {
        return match ($this) {
            self::Created => __('Ticket created'),
            self::Updated => __('Last update'),
            self::CustomerReply => __('Last customer reply'),
            self::AgentReply => __('Last agent reply'),
        };
    }

    public function column(): string
    {
        return match ($this) {
            self::Created => 'created_at',
            self::Updated => 'updated_at',
            self::CustomerReply => 'last_customer_reply_at',
            self::AgentReply => 'last_agent_reply_at',
        };
    }
}
