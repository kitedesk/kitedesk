<?php

namespace App\Domain\Audit\Concerns;

use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Records admin changes to a configuration model in the audit log: what was created, deleted
 * or changed, from what to what. Hidden attributes (credentials, secrets) are never recorded,
 * nor is the order things are listed in.
 */
trait Auditable
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('admin')
            ->logFillable()
            ->logExcept([...$this->getHidden(), 'position', 'created_at', 'updated_at', ...$this->notAudited()])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /**
     * Attributes that change on their own, too often or too uninteresting to record.
     *
     * @return list<string>
     */
    protected function notAudited(): array
    {
        return [];
    }
}
