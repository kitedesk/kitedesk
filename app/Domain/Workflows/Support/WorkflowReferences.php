<?php

namespace App\Domain\Workflows\Support;

use App\Domain\Workflows\Models\Workflow;

/**
 * Switches off active workflows whose steps use something that was deleted (a group to move
 * tickets to, an agent to assign or notify, a category, a custom status), so they don't run half-broken.
 */
class WorkflowReferences
{
    /**
     * Node settings that hold ids of each kind of record.
     */
    private const array KEYS = [
        'group' => ['group_id'],
        'user' => ['assignee_id', 'user_ids'],
        'category' => ['category_id'],
        'status' => ['ticket_status_id'],
    ];

    /**
     * @param  'group'|'user'|'category'|'status'  $kind
     */
    public static function deleted(string $kind, int $id, string $name): void
    {
        Workflow::query()->active()->get()->each(function (Workflow $workflow) use ($kind, $id, $name): void {
            if (! self::uses($workflow, $kind, $id)) {
                return;
            }

            $workflow->update([
                'is_active' => false,
                'disabled_reason' => __('Turned off because ":name" was deleted. Update the workflow and turn it on again.', ['name' => $name]),
            ]);
        });
    }

    /**
     * @param  'group'|'user'|'category'|'status'  $kind
     */
    private static function uses(Workflow $workflow, string $kind, int $id): bool
    {
        foreach ($workflow->graph['nodes'] as $node) {
            foreach (self::KEYS[$kind] as $key) {
                $value = $node['data'][$key] ?? null;
                $ids = is_array($value) ? $value : [$value];

                if (in_array((string) $id, array_map(fn (mixed $item): string => is_scalar($item) ? (string) $item : '', $ids), true)) {
                    return true;
                }
            }
        }

        return false;
    }
}
