<?php

namespace App\Domain\Tickets\Enums;

enum TicketChannel: string
{
    case Portal = 'portal';
    case Agent = 'agent';
    case Api = 'api';
    case Email = 'email';
    case Widget = 'widget';

    public function label(): string
    {
        return match ($this) {
            self::Portal => __('Customer portal'),
            self::Agent => __('Agent workspace'),
            self::Api => __('API'),
            self::Email => __('Email'),
            self::Widget => __('Website widget'),
        };
    }
}
