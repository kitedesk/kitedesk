<?php

namespace App\Domain\Workflows\Engine;

use App\Domain\Workflows\Nodes\AddCcNode;
use App\Domain\Workflows\Nodes\AddMessageNode;
use App\Domain\Workflows\Nodes\AiClassifyNode;
use App\Domain\Workflows\Nodes\AiNoteNode;
use App\Domain\Workflows\Nodes\AiPromptNode;
use App\Domain\Workflows\Nodes\AutoAssignNode;
use App\Domain\Workflows\Nodes\ChangeTagsNode;
use App\Domain\Workflows\Nodes\FilterNode;
use App\Domain\Workflows\Nodes\ForEachNode;
use App\Domain\Workflows\Nodes\HttpRequestNode;
use App\Domain\Workflows\Nodes\IfNode;
use App\Domain\Workflows\Nodes\NotifyNode;
use App\Domain\Workflows\Nodes\SendEmailNode;
use App\Domain\Workflows\Nodes\SetVariableNode;
use App\Domain\Workflows\Nodes\StopNode;
use App\Domain\Workflows\Nodes\SwitchNode;
use App\Domain\Workflows\Nodes\TriggerNode;
use App\Domain\Workflows\Nodes\UpdateTicketNode;
use App\Domain\Workflows\Nodes\WaitForReplyNode;
use App\Domain\Workflows\Nodes\WaitNode;
use App\Domain\Workflows\Nodes\WorkflowNode;
use Illuminate\Contracts\Container\Container;

/**
 * Every node type a workflow graph can contain.
 */
class NodeRegistry
{
    /**
     * type => [class, constructor parameters]
     *
     * @var array<string, array{class-string<WorkflowNode>, array<string, mixed>}>
     */
    private const array TYPES = [
        'trigger' => [TriggerNode::class, []],
        'if' => [IfNode::class, []],
        'switch' => [SwitchNode::class, []],
        'for_each' => [ForEachNode::class, []],
        'filter' => [FilterNode::class, []],
        'set_variable' => [SetVariableNode::class, []],
        'wait' => [WaitNode::class, []],
        'wait_for_reply' => [WaitForReplyNode::class, []],
        'stop' => [StopNode::class, []],
        'update_ticket' => [UpdateTicketNode::class, []],
        'add_tags' => [ChangeTagsNode::class, ['mode' => 'add']],
        'remove_tags' => [ChangeTagsNode::class, ['mode' => 'remove']],
        'auto_assign' => [AutoAssignNode::class, []],
        'add_note' => [AddMessageNode::class, ['isInternal' => true]],
        'reply' => [AddMessageNode::class, ['isInternal' => false]],
        'send_email' => [SendEmailNode::class, []],
        'notify' => [NotifyNode::class, []],
        'add_cc' => [AddCcNode::class, []],
        'http_request' => [HttpRequestNode::class, []],
        'ai_classify' => [AiClassifyNode::class, []],
        'ai_prompt' => [AiPromptNode::class, []],
        'ai_summary_note' => [AiNoteNode::class, ['kind' => 'summary']],
        'ai_draft_note' => [AiNoteNode::class, ['kind' => 'draft']],
    ];

    /**
     * Node types that pause a run.
     */
    public const array WAITING_TYPES = ['wait', 'wait_for_reply'];

    /**
     * @var array<string, WorkflowNode>
     */
    private array $instances = [];

    public function __construct(private Container $container) {}

    public function has(string $type): bool
    {
        return isset(self::TYPES[$type]);
    }

    public function get(string $type): ?WorkflowNode
    {
        if (! $this->has($type)) {
            return null;
        }

        [$class, $parameters] = self::TYPES[$type];

        return $this->instances[$type] ??= $this->container->make($class, $parameters);
    }

    /**
     * @return list<string>
     */
    public function types(): array
    {
        return array_keys(self::TYPES);
    }
}
