<?php

namespace App\Domain\Workflows\Models;

use App\Domain\Workflows\Enums\StepStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What one node did during a run: the branch it took and a short log of its effect.
 *
 * @property int $id
 * @property int $workflow_run_id
 * @property string $node_id
 * @property string $node_type
 * @property StepStatus $status
 * @property string|null $handle The output the run continued from.
 * @property array<string, mixed>|null $output
 * @property int|null $iteration Loop iteration (1-based) when the node ran inside a "For each".
 * @property string|null $error
 * @property CarbonImmutable $executed_at
 * @property-read WorkflowRun $run
 */
#[Fillable(['workflow_run_id', 'node_id', 'node_type', 'status', 'handle', 'output', 'iteration', 'error', 'executed_at'])]
class WorkflowRunStep extends Model
{
    public $timestamps = false;

    /**
     * @return BelongsTo<WorkflowRun, $this>
     */
    public function run(): BelongsTo
    {
        return $this->belongsTo(WorkflowRun::class, 'workflow_run_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => StepStatus::class,
            'output' => 'array',
            'iteration' => 'integer',
            'executed_at' => 'datetime',
        ];
    }
}
