import type { Edge, Node } from '@xyflow/react';
import type {
    WorkflowGraph,
    WorkflowNodeData,
    WorkflowNodeType,
    WorkflowStep,
} from '@/types';

export type FlowNode = Node<WorkflowNodeData, WorkflowNodeType>;

export const toFlowNodes = (graph: WorkflowGraph): FlowNode[] =>
    graph.nodes.map((node) => ({
        id: node.id,
        type: node.type,
        position: node.position,
        data: node.data,
        deletable: node.type !== 'trigger',
    }));

export const toFlowEdges = (graph: WorkflowGraph): Edge[] =>
    graph.edges.map((edge) => ({
        id: edge.id,
        source: edge.source,
        sourceHandle: edge.sourceHandle,
        target: edge.target,
    }));

export const toGraph = (nodes: FlowNode[], edges: Edge[]): WorkflowGraph => ({
    nodes: nodes.map((node) => ({
        id: node.id,
        type: node.type as WorkflowNodeType,
        position: {
            x: Math.round(node.position.x),
            y: Math.round(node.position.y),
        },
        data: node.data,
    })),
    edges: edges.map((edge) => ({
        id: edge.id,
        source: edge.source,
        sourceHandle: edge.sourceHandle ?? 'out',
        target: edge.target,
    })),
});

export const newNodeId = (): string =>
    `n${Date.now().toString(36)}${Math.random().toString(36).slice(2, 6)}`;

const reachable = (
    start: string | undefined,
    edges: { source: string; target: string }[],
): Set<string> => {
    const seen = new Set<string>();
    const queue = start ? [start] : [];

    while (queue.length) {
        const id = queue.shift() as string;

        if (seen.has(id)) {
            continue;
        }

        seen.add(id);
        edges
            .filter((edge) => edge.source === id)
            .forEach((edge) => queue.push(edge.target));
    }

    return seen;
};

/**
 * How many "For each" loops each node runs inside (the loop body is everything reachable
 * from a loop's "each" output).
 */
export function loopDepths(
    nodes: { id: string; type?: string }[],
    edges: { source: string; sourceHandle?: string | null; target: string }[],
): Map<string, number> {
    const depths = new Map<string, number>();

    nodes
        .filter((node) => node.type === 'for_each')
        .forEach((loop) => {
            const start = edges.find(
                (edge) =>
                    edge.source === loop.id && edge.sourceHandle === 'each',
            )?.target;

            reachable(start, edges).forEach((id) =>
                depths.set(id, (depths.get(id) ?? 0) + 1),
            );
        });

    return depths;
}

export type RunPath = {
    nodes: Map<string, WorkflowStep>;
    outputs: Set<string>;
};

/**
 * The nodes a run (or test) visited, with their last step, and the outputs it followed.
 */
export function runPath(steps: WorkflowStep[]): RunPath {
    const nodes = new Map<string, WorkflowStep>();
    const outputs = new Set<string>();

    steps.forEach((step) => {
        const previous = nodes.get(step.node_id);

        if (!previous || previous.status !== 'failed') {
            nodes.set(step.node_id, step);
        }

        if (step.node_type === 'for_each') {
            outputs.add(`${step.node_id}:each`);
            outputs.add(`${step.node_id}:done`);
        } else if (step.handle) {
            outputs.add(`${step.node_id}:${step.handle}`);
        }
    });

    return { nodes, outputs };
}
