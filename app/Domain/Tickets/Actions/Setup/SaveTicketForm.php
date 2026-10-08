<?php

namespace App\Domain\Tickets\Actions\Setup;

use App\Domain\Entitlements\Enums\Limit;
use App\Domain\Entitlements\Support\PlanLimits;
use App\Domain\Tickets\Models\TicketForm;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates or updates a ticket form and its ordered fields. Only one form is the default.
 */
class SaveTicketForm
{
    /**
     * @param  array<string, mixed>  $attributes  From `SaveTicketFormRequest::formAttributes()`.
     * @param  list<array{id: int, is_required: bool}>  $fields
     *
     * @throws ValidationException when the plan has no room for another form
     */
    public function create(array $attributes, array $fields): TicketForm
    {
        PlanLimits::ensureRoomFor(Limit::TicketForms, 'name');

        return $this->save(new TicketForm, $attributes, $fields);
    }

    /**
     * @param  array<string, mixed>  $attributes  From `SaveTicketFormRequest::formAttributes()`.
     * @param  list<array{id: int, is_required: bool}>  $fields
     */
    public function update(TicketForm $form, array $attributes, array $fields): TicketForm
    {
        return $this->save($form, $attributes, $fields);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<array{id: int, is_required: bool}>  $fields
     */
    private function save(TicketForm $form, array $attributes, array $fields): TicketForm
    {
        return DB::transaction(function () use ($form, $attributes, $fields): TicketForm {
            $form->fill($attributes)->save();
            $form->syncFields($fields);

            if ($form->is_default) {
                TicketForm::query()->whereKeyNot($form->id)->where('is_default', true)->update(['is_default' => false]);
            }

            return $form;
        });
    }
}
