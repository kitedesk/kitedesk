<?php

namespace App\Domain\Tickets\Enums;

enum TicketType: string
{
    case Question = 'question';
    case Incident = 'incident';
    case Problem = 'problem';
    case Task = 'task';

    public function label(): string
    {
        return match ($this) {
            self::Question => __('Question'),
            self::Incident => __('Incident'),
            self::Problem => __('Problem'),
            self::Task => __('Task'),
        };
    }
}
