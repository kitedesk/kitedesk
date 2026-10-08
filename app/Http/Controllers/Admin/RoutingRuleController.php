<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Accounts\Models\Group;
use App\Domain\Accounts\Models\Organization;
use App\Domain\Support\EnumOptions;
use App\Domain\Support\Positions;
use App\Domain\Tickets\Enums\RoutingOperator;
use App\Domain\Tickets\Enums\TicketChannel;
use App\Domain\Tickets\Enums\TicketPriority;
use App\Domain\Tickets\Enums\TicketType;
use App\Domain\Tickets\Models\RoutingRule;
use App\Domain\Tickets\Models\TicketField;
use App\Domain\Tickets\Support\TicketCatalog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveRoutingRuleRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RoutingRuleController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/routing-rules/index', [
            'rules' => RoutingRule::query()->ordered()->get()->map(fn (RoutingRule $rule): array => $this->serialize($rule)),
            'options' => $this->options(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/routing-rules/form', [
            'rule' => null,
            'options' => $this->options(),
        ]);
    }

    public function store(SaveRoutingRuleRequest $request): RedirectResponse
    {
        RoutingRule::query()->create([
            ...$request->ruleAttributes(),
            'position' => (int) RoutingRule::query()->max('position') + 1,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Routing rule created.')]);

        return to_route('admin.routing-rules.index');
    }

    public function edit(RoutingRule $routingRule): Response
    {
        return Inertia::render('admin/routing-rules/form', [
            'rule' => $this->serialize($routingRule),
            'options' => $this->options(),
        ]);
    }

    public function update(SaveRoutingRuleRequest $request, RoutingRule $routingRule): RedirectResponse
    {
        $routingRule->update($request->ruleAttributes());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Routing rule saved.')]);

        return to_route('admin.routing-rules.index');
    }

    public function destroy(RoutingRule $routingRule): RedirectResponse
    {
        $routingRule->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Routing rule deleted.')]);

        return to_route('admin.routing-rules.index');
    }

    public function toggle(RoutingRule $routingRule): RedirectResponse
    {
        $routingRule->update(['is_active' => ! $routingRule->is_active]);

        return back();
    }

    /**
     * Move a rule one step up or down; rules are evaluated top to bottom.
     */
    public function move(Request $request, RoutingRule $routingRule): RedirectResponse
    {
        /** @var 'up'|'down' $direction */
        $direction = $request->validate(['direction' => ['required', 'in:up,down']])['direction'];

        Positions::move(RoutingRule::query(), $routingRule, $direction);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(RoutingRule $rule): array
    {
        return [
            'id' => $rule->id,
            'name' => $rule->name,
            'is_active' => $rule->is_active,
            'match' => $rule->match,
            'conditions' => $rule->conditions,
            'actions' => [
                'group_id' => $rule->actions['group_id'],
                'priority' => $rule->actions['priority'] ?? null,
                'tags' => $rule->actions['tags'] ?? [],
            ],
        ];
    }

    /**
     * Everything the condition builder needs to offer fields, operators and values.
     *
     * @return array<string, mixed>
     */
    private function options(): array
    {
        return [
            'operators' => EnumOptions::for(RoutingOperator::class),
            'categories' => TicketCatalog::categories(),
            'groups' => Group::query()->orderBy('name')->get(['id', 'name']),
            'organizations' => Organization::query()->orderBy('name')->get(['id', 'name']),
            'priorities' => EnumOptions::for(TicketPriority::class),
            'types' => EnumOptions::for(TicketType::class),
            'channels' => EnumOptions::for(TicketChannel::class),
            'fields' => TicketField::query()->ordered()->get()->map(fn (TicketField $field): array => $field->toFormArray(required: false)),
        ];
    }
}
