<?php

namespace App\Domain\Tickets\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @property int $ticket_form_id
 * @property int $ticket_field_id
 * @property int $position
 * @property bool $is_required
 */
class TicketFormFieldPivot extends Pivot
{
    public $incrementing = true;

    public $timestamps = false;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'is_required' => 'boolean',
        ];
    }
}
