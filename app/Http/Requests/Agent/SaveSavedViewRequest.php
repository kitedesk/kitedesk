<?php

namespace App\Http\Requests\Agent;

use App\Domain\Accounts\Enums\Permission;
use App\Domain\Tickets\Support\BoardPreferences;
use App\Domain\Tickets\Support\TicketFilters;
use App\Domain\Tickets\Support\TicketViews;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

/**
 * Saves the queue's current view and filters under a name. Only admins may share a view.
 */
class SaveSavedViewRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $creating = $this->route('view') === null;

        return [
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:80'],
            'is_shared' => ['sometimes', 'boolean', Rule::prohibitedIf(fn (): bool => $this->boolean('is_shared') && ! $this->user()->hasPermission(Permission::ShareViews))],
            'view' => [$creating ? 'required' : 'prohibited', 'string', 'max:40'],
            'filter' => [$creating ? 'nullable' : 'prohibited', 'array'],
            'filter.*' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', Rule::in(['updated_at', '-updated_at', 'created_at', '-created_at', 'id', '-id'])],
            'layout' => ['nullable', Rule::in(BoardPreferences::LAYOUTS)],
        ];
    }

    /**
     * The filters to store: the base view's (when saving from a saved view) plus the queue's
     * current ones. Picking yourself as assignee is stored as "me", so the view follows whoever opens it.
     *
     * @return array<string, string>
     */
    public function storedFilters(): array
    {
        $view = $this->string('view')->toString();
        $saved = TicketViews::savedView($view, $this->user());
        $base = $saved !== null ? ($saved->filters['view'] ?? 'all') : TicketViews::resolve($view);

        /** @var array<string, string|null> $current */
        $current = (array) $this->validated('filter', []);

        $filters = collect([...Arr::except($saved->filters ?? [], 'view'), ...$current])
            ->only(TicketFilters::KEYS)
            ->filter(fn (mixed $value): bool => $value !== null && $value !== '')
            ->map(fn (mixed $value): string => (string) $value);

        if ($filters->get('assignee_id') === (string) $this->user()->id) {
            $filters->put('assignee_id', TicketFilters::ME);
        }

        return ['view' => $base, ...$filters->all()];
    }
}
