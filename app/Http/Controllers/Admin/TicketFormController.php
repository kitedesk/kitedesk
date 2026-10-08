<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Tickets\Actions\Setup\SaveTicketForm;
use App\Domain\Tickets\Models\TicketField;
use App\Domain\Tickets\Models\TicketForm;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveTicketFormRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class TicketFormController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/ticket-forms/index', [
            'forms' => TicketForm::query()
                ->withCount(['fields', 'categories'])
                ->orderByDesc('is_default')
                ->orderBy('id')
                ->get()
                ->map(fn (TicketForm $form): array => [
                    'id' => $form->id,
                    'name' => $form->name,
                    'description' => $form->description ?? '',
                    'is_default' => $form->is_default,
                    'is_active' => $form->is_active,
                    'fields_count' => $form->fields_count,
                    'categories_count' => $form->categories_count,
                ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/ticket-forms/form', [
            'form' => null,
            'fields' => $this->availableFields(),
        ]);
    }

    public function store(SaveTicketFormRequest $request, SaveTicketForm $saveForm): RedirectResponse
    {
        $saveForm->create($request->formAttributes(), $request->fields());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Form created.')]);

        return to_route('admin.ticket-forms.index');
    }

    public function edit(TicketForm $ticketForm): Response
    {
        $ticketForm->load('fields');

        return Inertia::render('admin/ticket-forms/form', [
            'form' => [
                'id' => $ticketForm->id,
                'name' => $ticketForm->name,
                'description' => $ticketForm->description,
                'is_default' => $ticketForm->is_default,
                'is_active' => $ticketForm->is_active,
                'fields' => $ticketForm->fields->map(fn (TicketField $field): array => [
                    'id' => $field->id,
                    'is_required' => (bool) $field->pivot?->is_required,
                ])->values(),
            ],
            'fields' => $this->availableFields(),
        ]);
    }

    public function update(SaveTicketFormRequest $request, TicketForm $ticketForm, SaveTicketForm $saveForm): RedirectResponse
    {
        $saveForm->update($ticketForm, $request->formAttributes(), $request->fields());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Form saved.')]);

        return to_route('admin.ticket-forms.index');
    }

    public function destroy(TicketForm $ticketForm): RedirectResponse
    {
        $ticketForm->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Form deleted. Categories that used it now use the default form.')]);

        return to_route('admin.ticket-forms.index');
    }

    /**
     * @return list<array{id: int, key: string, label: string, type: string, is_visible_to_customers: bool}>
     */
    private function availableFields(): array
    {
        return array_values(TicketField::query()->ordered()->get()->map(fn (TicketField $field): array => [
            'id' => $field->id,
            'key' => $field->key,
            'label' => $field->label,
            'type' => $field->type->value,
            'is_visible_to_customers' => $field->is_visible_to_customers,
        ])->all());
    }
}
