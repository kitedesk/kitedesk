<?php

namespace App\Domain\Tickets\Actions\Setup;

use App\Domain\Entitlements\Enums\Limit;
use App\Domain\Entitlements\Support\PlanLimits;
use App\Domain\Tickets\Models\TicketField;
use Illuminate\Validation\ValidationException;

/**
 * Creates or updates a custom ticket field. New fields go last.
 */
class SaveTicketField
{
    /**
     * @param  array<string, mixed>  $attributes  Validated by `SaveTicketFieldRequest`.
     *
     * @throws ValidationException when the plan has no room for another field
     */
    public function create(array $attributes): TicketField
    {
        PlanLimits::ensureRoomFor(Limit::CustomFields, 'label');

        return TicketField::query()->create([
            ...$attributes,
            'position' => (int) TicketField::query()->max('position') + 1,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes  Validated by `SaveTicketFieldRequest`.
     */
    public function update(TicketField $field, array $attributes): TicketField
    {
        $field->update($attributes);

        return $field;
    }
}
