<?php

namespace App\Domain\Jira\Enums;

/**
 * The three groups Jira puts every workflow status in. The status mapping works on these,
 * so it keeps working whatever statuses a Jira project's workflow has.
 */
enum StatusCategory: string
{
    case ToDo = 'new';
    case InProgress = 'indeterminate';
    case Done = 'done';

    public function label(): string
    {
        return match ($this) {
            self::ToDo => __('To do'),
            self::InProgress => __('In progress'),
            self::Done => __('Done'),
        };
    }
}
