<?php

namespace App\Http\Controllers\Admin\Sla;

use App\Domain\Accounts\Models\Group;
use App\Domain\Accounts\Models\Organization;
use App\Domain\Sla\Models\BusinessSchedule;
use App\Domain\Sla\Models\SlaPolicy;
use App\Domain\Support\EnumOptions;
use App\Domain\Tickets\Enums\TicketPriority;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Sla\SaveSlaPolicyRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SlaPolicyController extends Controller
{
    public function index(): Response
    {
        $groups = Group::query()->pluck('name', 'id');
        $organizations = Organization::query()->pluck('name', 'id');

        return Inertia::render('admin/sla/policies/index', [
            'policies' => SlaPolicy::query()
                ->with('businessSchedule:id,name')
                ->orderBy('position')
                ->orderBy('id')
                ->get()
                ->map(fn (SlaPolicy $policy): array => [
                    ...$this->serialize($policy),
                    'business_schedule' => $policy->businessSchedule?->only(['id', 'name']),
                    'condition_labels' => [
                        'priorities' => array_map(fn (string $value): string => TicketPriority::from($value)->label(), $policy->conditions['priorities'] ?? []),
                        'groups' => array_values(array_filter(array_map(fn (int $id): ?string => $groups[$id] ?? null, $policy->conditions['group_ids'] ?? []))),
                        'organizations' => array_values(array_filter(array_map(fn (int $id): ?string => $organizations[$id] ?? null, $policy->conditions['organization_ids'] ?? []))),
                    ],
                ]),
            'schedulesCount' => BusinessSchedule::query()->count(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/sla/policies/form', [
            'policy' => null,
            'options' => $this->options(),
        ]);
    }

    public function store(SaveSlaPolicyRequest $request): RedirectResponse
    {
        SlaPolicy::query()->create([
            ...$this->attributes($request),
            'is_active' => $request->boolean('is_active', true),
            'position' => (int) SlaPolicy::query()->max('position') + 1,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('SLA policy created.')]);

        return to_route('admin.sla-policies.index');
    }

    public function edit(SlaPolicy $policy): Response
    {
        return Inertia::render('admin/sla/policies/form', [
            'policy' => $this->serialize($policy),
            'options' => $this->options(),
        ]);
    }

    public function update(SaveSlaPolicyRequest $request, SlaPolicy $policy): RedirectResponse
    {
        $policy->update([
            ...$this->attributes($request),
            'is_active' => $request->boolean('is_active', $policy->is_active),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('SLA policy updated.')]);

        return to_route('admin.sla-policies.index');
    }

    public function destroy(SlaPolicy $policy): RedirectResponse
    {
        $policy->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('SLA policy deleted.')]);

        return to_route('admin.sla-policies.index');
    }

    public function toggle(SlaPolicy $policy): RedirectResponse
    {
        $policy->update(['is_active' => ! $policy->is_active]);

        return back();
    }

    /**
     * Swap the policy with its neighbour so admins can control evaluation order.
     */
    public function move(Request $request, SlaPolicy $policy): RedirectResponse
    {
        $direction = $request->validate(['direction' => ['required', 'in:up,down']])['direction'];

        DB::transaction(function () use ($policy, $direction): void {
            $ordered = SlaPolicy::query()->orderBy('position')->orderBy('id')->lockForUpdate()->get()->values();

            // Normalize positions first so swapping is always well defined.
            $ordered->each(fn (SlaPolicy $item, int $index) => $item->position === $index ? null : $item->forceFill(['position' => $index])->save());

            $index = $ordered->search(fn (SlaPolicy $item): bool => $item->is($policy));
            $neighbourIndex = $direction === 'up' ? $index - 1 : $index + 1;
            $neighbour = $ordered->get($neighbourIndex);

            if ($index === false || $neighbour === null) {
                return;
            }

            $current = $ordered->get($index);
            $current->forceFill(['position' => $neighbourIndex])->save();
            $neighbour->forceFill(['position' => $index])->save();
        });

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(SaveSlaPolicyRequest $request): array
    {
        return [
            'name' => $request->string('name')->toString(),
            'description' => $request->validated('description'),
            'business_schedule_id' => $request->validated('business_schedule_id'),
            'conditions' => $request->conditions(),
            'targets' => $request->targets(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(SlaPolicy $policy): array
    {
        return [
            'id' => $policy->id,
            'name' => $policy->name,
            'description' => $policy->description,
            'business_schedule_id' => $policy->business_schedule_id,
            'conditions' => [
                'priorities' => $policy->conditions['priorities'] ?? [],
                'group_ids' => $policy->conditions['group_ids'] ?? [],
                'organization_ids' => $policy->conditions['organization_ids'] ?? [],
            ],
            'targets' => $policy->targets,
            'position' => $policy->position,
            'is_active' => $policy->is_active,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function options(): array
    {
        return [
            'priorities' => EnumOptions::for(TicketPriority::class),
            'groups' => Group::query()->orderBy('name')->get(['id', 'name']),
            'organizations' => Organization::query()->orderBy('name')->get(['id', 'name']),
            'schedules' => BusinessSchedule::query()->orderBy('name')->get(['id', 'name', 'timezone']),
        ];
    }
}
