<?php

namespace App\Domain\Workflows\Enums;

enum RunStatus: string
{
    case Running = 'running';
    case Waiting = 'waiting';
    case Completed = 'completed';
    case Stopped = 'stopped';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Running => __('Running'),
            self::Waiting => __('Waiting'),
            self::Completed => __('Completed'),
            self::Stopped => __('Stopped'),
            self::Failed => __('Failed'),
            self::Cancelled => __('Cancelled'),
        };
    }

    public function isFinished(): bool
    {
        return ! in_array($this, [self::Running, self::Waiting], true);
    }
}
