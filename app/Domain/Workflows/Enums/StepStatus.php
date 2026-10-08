<?php

namespace App\Domain\Workflows\Enums;

enum StepStatus: string
{
    case Succeeded = 'succeeded';
    case Skipped = 'skipped';
    case Waiting = 'waiting';
    case Failed = 'failed';
}
