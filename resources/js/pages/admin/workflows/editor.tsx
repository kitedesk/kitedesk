import { Head, Link, router } from '@inertiajs/react';
import {
    addEdge,
    Background,
    BackgroundVariant,
    Controls,
    MiniMap,
    ReactFlow,
    ReactFlowProvider,
    useEdgesState,
    useNodesState,
    useReactFlow,
} from '@xyflow/react';
import type {
    Connection,
    Edge,
    IsValidConnection,
    NodeTypes,
} from '@xyflow/react';
import '@xyflow/react/dist/style.css';
import {
    AlertCircle,
    ArrowLeft,
    FlaskConical,
    Save,
    Settings2,
} from 'lucide-react';
import { useCallback, useMemo, useState } from 'react';
import type { DragEvent } from 'react';
import { ConfirmAction } from '@/components/admin/confirm-action';
import { TextField } from '@/components/admin/text-field';
import { ActiveSwitch } from '@/components/sla/active-switch';
import type { TicketMatch } from '@/components/tickets/ticket-search';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { EditorContext } from '@/components/workflows/editor-context';
import type { EditorContextValue } from '@/components/workflows/editor-context';
import { ExecutionsPanel } from '@/components/workflows/executions-panel';
import type { FlowNode } from '@/components/workflows/graph-utils';
import {
    loopDepths,
    newNodeId,
    runPath,
    toFlowEdges,
    toFlowNodes,
    toGraph,
} from '@/components/workflows/graph-utils';
import { NodeCard } from '@/components/workflows/node-card';
import { NodeContextMenu } from '@/components/workflows/node-context-menu';
import type { ContextMenuPosition } from '@/components/workflows/node-context-menu';
import type { NodeOutput } from '@/components/workflows/node-catalog';
import { NODE_DEFINITIONS } from '@/components/workflows/node-catalog';
import { NodeInspector } from '@/components/workflows/node-inspector';
import { DRAG_TYPE, NodePalette } from '@/components/workflows/node-palette';
import { TestPanel } from '@/components/workflows/test-panel';
import { useTranslation } from '@/hooks/use-translation';
import { postJson } from '@/lib/post-json';
import { cn } from '@/lib/utils';
import { destroy, index, store, test, update } from '@/routes/admin/workflows';
import type {
    EditableWorkflow,
    SelectedRun,
    SimulationResult,
    WorkflowNodeData,
    WorkflowNodeType,
    WorkflowOptions,
    WorkflowRunSummary,
} from '@/types';

type Props = {
    workflow: EditableWorkflow;
    options: WorkflowOptions;
    runs: WorkflowRunSummary[];
    selectedRun: SelectedRun | null;
};

const nodeTypes = Object.fromEntries(
    Object.keys(NODE_DEFINITIONS).map((type) => [type, NodeCard]),
) as NodeTypes;

type Panel = 'inspector' | 'test';

function WorkflowEditor({ workflow, options, runs, selectedRun }: Props) {
    const { t } = useTranslation();
    const flow = useReactFlow<FlowNode, Edge>();
    const [nodes, setNodes, onNodesChange] = useNodesState<FlowNode>(
        toFlowNodes(workflow.graph),
    );
    const [edges, setEdges, onEdgesChange] = useEdgesState<Edge>(
        toFlowEdges(workflow.graph),
    );
    const [meta, setMeta] = useState({
        name: workflow.name,
        description: workflow.description ?? '',
        is_active: workflow.is_active,
        max_runs_per_ticket: workflow.max_runs_per_ticket,
        apply_to_existing: workflow.apply_to_existing,
    });
    const [mode, setMode] = useState<'editor' | 'executions'>(
        selectedRun ? 'executions' : 'editor',
    );
    const [selectedId, setSelectedId] = useState<string | null>(null);
    const [panel, setPanel] = useState<Panel>('inspector');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [saving, setSaving] = useState(false);
    const [dirty, setDirty] = useState(workflow.id === null);
    const [settingsOpen, setSettingsOpen] = useState(false);
    const [picker, setPicker] = useState<{
        nodeId: string;
        output: NodeOutput;
    } | null>(null);
    const [contextMenu, setContextMenu] = useState<ContextMenuPosition | null>(
        null,
    );
    const [testing, setTesting] = useState(false);
    const [testResult, setTestResult] = useState<SimulationResult | null>(null);

    const executions = mode === 'executions';
    const shownGraph = executions && selectedRun ? selectedRun.graph : null;
    const shownNodes = useMemo(
        () => (shownGraph ? toFlowNodes(shownGraph) : nodes),
        [shownGraph, nodes],
    );
    const shownEdges = useMemo(
        () => (shownGraph ? toFlowEdges(shownGraph) : edges),
        [shownGraph, edges],
    );
    const steps = executions
        ? (selectedRun?.steps ?? null)
        : panel === 'test'
          ? (testResult?.steps ?? null)
          : null;
    const path = useMemo(() => (steps ? runPath(steps) : null), [steps]);
    const trigger = nodes.find((node) => node.type === 'trigger');

    const nodeErrors = useMemo(
        () =>
            Object.fromEntries(
                Object.entries(errors)
                    .filter(([key]) => key.startsWith('nodes.'))
                    .map(([key, message]) => [key.slice(6), message]),
            ),
        [errors],
    );
    const generalErrors = Object.entries(errors)
        .filter(([key]) => !key.startsWith('nodes.'))
        .map(([, message]) => message);

    const variables = useMemo(
        () => [
            ...new Set(
                nodes
                    .map((node) =>
                        node.type === 'set_variable'
                            ? node.data.name
                            : node.type === 'http_request' ||
                                node.type === 'ai_classify' ||
                                node.type === 'ai_prompt'
                              ? node.data.save_as
                              : null,
                    )
                    .filter(
                        (name): name is string =>
                            typeof name === 'string' && name !== '',
                    ),
            ),
        ],
        [nodes],
    );

    const markDirty = () => {
        setDirty(true);
        setTestResult(null);
    };

    const addNode = useCallback(
        (
            type: WorkflowNodeType,
            position: { x: number; y: number },
            from?: { nodeId: string; output: string },
            data?: WorkflowNodeData,
        ) => {
            const id = newNodeId();

            setNodes((current) => [
                ...current.map((node) => ({ ...node, selected: false })),
                {
                    id,
                    type,
                    position,
                    data: data ?? NODE_DEFINITIONS[type].defaults(),
                    selected: true,
                },
            ]);

            if (from) {
                setEdges((current) => [
                    ...current.filter(
                        (edge) =>
                            !(
                                edge.source === from.nodeId &&
                                edge.sourceHandle === from.output
                            ),
                    ),
                    {
                        id: `${from.nodeId}-${from.output}-${id}`,
                        source: from.nodeId,
                        sourceHandle: from.output,
                        target: id,
                    },
                ]);
            }

            setSelectedId(id);
            setPanel('inspector');
            markDirty();
        },
        [setNodes, setEdges],
    );

    const addBelow = (type: WorkflowNodeType) => {
        const anchor =
            nodes.find((node) => node.id === selectedId) ??
            nodes[nodes.length - 1];
        const position = anchor
            ? { x: anchor.position.x, y: anchor.position.y + 180 }
            : flow.screenToFlowPosition({
                  x: window.innerWidth / 2,
                  y: window.innerHeight / 2,
              });

        addNode(type, position);
    };

    const onAddAfter = useCallback(
        (nodeId: string, output: NodeOutput) => setPicker({ nodeId, output }),
        [],
    );

    const pickAfter = (type: WorkflowNodeType) => {
        if (!picker) {
            return;
        }

        const source = nodes.find((node) => node.id === picker.nodeId);
        const outputs = source
            ? NODE_DEFINITIONS[source.type as WorkflowNodeType].outputs(
                  source.data,
              )
            : [];
        const offset =
            outputs.length > 1
                ? (outputs.findIndex(
                      (output) => output.id === picker.output.id,
                  ) -
                      (outputs.length - 1) / 2) *
                  300
                : 0;

        addNode(
            type,
            {
                x: (source?.position.x ?? 0) + offset,
                y: (source?.position.y ?? 0) + 200,
            },
            { nodeId: picker.nodeId, output: picker.output.id },
        );
        setPicker(null);
    };

    const onConnect = useCallback(
        (connection: Connection) => {
            setEdges((current) =>
                addEdge(
                    {
                        ...connection,
                        id: `${connection.source}-${connection.sourceHandle ?? 'out'}-${connection.target}`,
                    },
                    current.filter(
                        (edge) =>
                            !(
                                edge.source === connection.source &&
                                edge.sourceHandle === connection.sourceHandle
                            ),
                    ),
                ),
            );
            markDirty();
        },
        [setEdges],
    );

    const isValidConnection: IsValidConnection<Edge> = (connection) =>
        connection.source !== connection.target &&
        nodes.find((node) => node.id === connection.target)?.type !== 'trigger';

    const onDrop = (event: DragEvent) => {
        event.preventDefault();
        const type = event.dataTransfer.getData(DRAG_TYPE) as WorkflowNodeType;

        if (type && NODE_DEFINITIONS[type]) {
            addNode(
                type,
                flow.screenToFlowPosition({
                    x: event.clientX - 128,
                    y: event.clientY - 30,
                }),
            );
        }
    };

    const updateNodeData = (id: string, data: WorkflowNodeData) => {
        setNodes((current) =>
            current.map((node) => (node.id === id ? { ...node, data } : node)),
        );
        markDirty();
    };

    const deleteNode = (id: string) => {
        setNodes((current) => current.filter((node) => node.id !== id));
        setEdges((current) =>
            current.filter((edge) => edge.source !== id && edge.target !== id),
        );
        setSelectedId(null);
        markDirty();
    };

    const openNode = (id: string) => {
        setSelectedId(id);
        setPanel('inspector');
    };

    const duplicateNode = (id: string) => {
        const source = nodes.find((node) => node.id === id);

        if (source && source.type !== 'trigger') {
            addNode(
                source.type as WorkflowNodeType,
                { x: source.position.x + 40, y: source.position.y + 40 },
                undefined,
                structuredClone(source.data),
            );
        }
    };

    const disconnectNode = (id: string) => {
        setEdges((current) =>
            current.filter((edge) => edge.source !== id && edge.target !== id),
        );
        markDirty();
    };

    const save = () => {
        const payload = {
            ...meta,
            description: meta.description || null,
            graph: toGraph(nodes, edges),
        };
        const visit = {
            preserveScroll: true,
            preserveState: true,
            onStart: () => setSaving(true),
            onFinish: () => setSaving(false),
            onSuccess: () => {
                setErrors({});
                setDirty(false);
            },
            onError: (bag: Record<string, string>) => setErrors(bag),
        };

        if (workflow.id === null) {
            router.post(store.url(), payload as never, visit);
        } else {
            router.put(update.url(workflow.id), payload as never, visit);
        }
    };

    const runTest = async (ticket: TicketMatch) => {
        setTesting(true);
        const response = await postJson<SimulationResult>(test.url(), {
            graph: toGraph(nodes, edges),
            ticket_id: ticket.id,
            name: meta.name,
        });
        setTesting(false);

        if (response.ok) {
            setErrors({});
            setTestResult(response.data);
        } else {
            setErrors(response.errors);
            setTestResult(null);
        }
    };

    const connectedOutputs = useMemo(
        () =>
            new Set(
                shownEdges.map(
                    (edge) => `${edge.source}:${edge.sourceHandle ?? 'out'}`,
                ),
            ),
        [shownEdges],
    );

    const context: EditorContextValue = {
        options,
        errors: executions ? {} : nodeErrors,
        steps: path?.nodes ?? null,
        loopDepths: useMemo(
            () => loopDepths(shownNodes, shownEdges),
            [shownNodes, shownEdges],
        ),
        readOnly: executions,
        onAddAfter,
        connectedOutputs,
    };

    const displayedEdges = useMemo(
        () =>
            shownEdges.map((edge) => {
                const followed =
                    path?.outputs.has(
                        `${edge.source}:${edge.sourceHandle ?? 'out'}`,
                    ) && path.nodes.has(edge.target);

                return followed
                    ? {
                          ...edge,
                          animated: true,
                          style: {
                              stroke: 'var(--color-emerald-500)',
                              strokeWidth: 2.5,
                          },
                      }
                    : path
                      ? { ...edge, style: { opacity: 0.35 } }
                      : edge;
            }),
        [shownEdges, path],
    );

    const selected = shownNodes.find((node) => node.id === selectedId);

    return (
        <EditorContext.Provider value={context}>
            <Head title={meta.name || t('Workflow')} />
            <div className="flex h-[calc(100svh-4rem)] min-h-[32rem] flex-col">
                <header className="flex flex-wrap items-center gap-2 border-b px-4 py-2">
                    <Button
                        variant="ghost"
                        size="icon"
                        asChild
                        title={t('Back to workflows')}
                    >
                        <Link href={index()}>
                            <ArrowLeft />
                        </Link>
                    </Button>
                    <Input
                        aria-label={t('Name')}
                        value={meta.name}
                        onChange={(event) => {
                            setMeta({ ...meta, name: event.target.value });
                            markDirty();
                        }}
                        className={cn(
                            'h-8 w-64 border-transparent font-medium shadow-none hover:border-input focus-visible:border-input',
                            errors.name && 'border-destructive',
                        )}
                    />
                    <label className="flex items-center gap-2 text-sm">
                        <ActiveSwitch
                            checked={meta.is_active}
                            label={t('Active')}
                            onChange={(is_active) => {
                                setMeta({ ...meta, is_active });
                                markDirty();
                            }}
                        />
                        <span className="text-muted-foreground">
                            {meta.is_active ? t('Active') : t('Inactive')}
                        </span>
                    </label>
                    {dirty && (
                        <span className="text-xs text-muted-foreground">
                            {t('Unsaved changes')}
                        </span>
                    )}

                    <div className="mx-auto flex rounded-lg border p-0.5 text-sm">
                        {(['editor', 'executions'] as const).map((item) => (
                            <button
                                key={item}
                                type="button"
                                onClick={() => {
                                    setMode(item);
                                    setSelectedId(null);
                                }}
                                className={cn(
                                    'rounded-md px-3 py-1 transition-colors',
                                    mode === item
                                        ? 'bg-accent font-medium'
                                        : 'text-muted-foreground hover:text-foreground',
                                )}
                            >
                                {item === 'editor'
                                    ? t('Editor')
                                    : t('Executions')}
                            </button>
                        ))}
                    </div>

                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => setSettingsOpen(true)}
                    >
                        <Settings2 /> {t('Settings')}
                    </Button>
                    {!executions && (
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => {
                                setPanel('test');
                                setSelectedId(null);
                            }}
                        >
                            <FlaskConical /> {t('Test')}
                        </Button>
                    )}
                    <Button size="sm" onClick={save} disabled={saving}>
                        {saving ? <Spinner /> : <Save />} {t('Save')}
                    </Button>
                </header>

                {(generalErrors.length > 0 || workflow.disabled_reason) && (
                    <div className="space-y-1 border-b bg-destructive/5 px-4 py-2 text-sm text-destructive">
                        {workflow.disabled_reason && !meta.is_active && (
                            <p>{workflow.disabled_reason}</p>
                        )}
                        {generalErrors.map((message) => (
                            <p
                                key={message}
                                className="flex items-center gap-2"
                            >
                                <AlertCircle className="size-4" /> {message}
                            </p>
                        ))}
                    </div>
                )}

                <div className="flex min-h-0 flex-1">
                    <aside className="hidden w-64 shrink-0 flex-col border-r p-3 md:flex">
                        {executions ? (
                            <ExecutionsPanel
                                workflowId={workflow.id}
                                runs={runs}
                                selectedId={selectedRun?.id ?? null}
                            />
                        ) : (
                            <NodePalette onPick={addBelow} />
                        )}
                    </aside>

                    <div
                        className="relative min-w-0 flex-1"
                        onContextMenu={(event) => event.preventDefault()}
                        onDragOver={(event) => event.preventDefault()}
                        onDrop={onDrop}
                    >
                        <ReactFlow<FlowNode, Edge>
                            nodes={shownNodes}
                            edges={displayedEdges}
                            nodeTypes={nodeTypes}
                            onNodesChange={(changes) => {
                                if (executions) {
                                    return;
                                }

                                onNodesChange(changes);

                                if (
                                    changes.some(
                                        (change) =>
                                            change.type === 'position' ||
                                            change.type === 'remove',
                                    )
                                ) {
                                    markDirty();
                                }
                            }}
                            onEdgesChange={(changes) => {
                                if (executions) {
                                    return;
                                }

                                onEdgesChange(changes);

                                if (
                                    changes.some(
                                        (change) => change.type === 'remove',
                                    )
                                ) {
                                    markDirty();
                                }
                            }}
                            onConnect={onConnect}
                            isValidConnection={isValidConnection}
                            onNodeClick={(_, node) => {
                                setSelectedId(node.id);
                                setPanel('inspector');
                            }}
                            onNodeContextMenu={(event, node) => {
                                event.preventDefault();
                                const bounds = event.currentTarget
                                    .closest('.react-flow')
                                    ?.getBoundingClientRect();
                                setContextMenu({
                                    nodeId: node.id,
                                    x: event.clientX - (bounds?.left ?? 0),
                                    y: event.clientY - (bounds?.top ?? 0),
                                });
                            }}
                            onMoveStart={() => setContextMenu(null)}
                            onPaneClick={() => setSelectedId(null)}
                            onNodesDelete={() => setSelectedId(null)}
                            nodesDraggable={!executions}
                            nodesConnectable={!executions}
                            deleteKeyCode={
                                executions ? null : ['Backspace', 'Delete']
                            }
                            snapToGrid
                            snapGrid={[20, 20]}
                            fitView
                            fitViewOptions={{ maxZoom: 1, padding: 0.3 }}
                            minZoom={0.2}
                            colorMode="system"
                            proOptions={{ hideAttribution: true }}
                        >
                            <Background
                                variant={BackgroundVariant.Dots}
                                gap={20}
                                size={1.2}
                            />
                            <Controls showInteractive={false} />
                            <MiniMap
                                pannable
                                zoomable
                                className="!hidden lg:!block"
                            />
                        </ReactFlow>
                        <NodeContextMenu
                            position={contextMenu}
                            node={shownNodes.find(
                                (node) => node.id === contextMenu?.nodeId,
                            )}
                            connectedOutputs={connectedOutputs}
                            hasConnections={shownEdges.some(
                                (edge) =>
                                    edge.source === contextMenu?.nodeId ||
                                    edge.target === contextMenu?.nodeId,
                            )}
                            readOnly={executions}
                            onClose={() => setContextMenu(null)}
                            onOpen={openNode}
                            onAddAfter={onAddAfter}
                            onDuplicate={duplicateNode}
                            onDisconnect={disconnectNode}
                            onDelete={deleteNode}
                        />
                        {executions && !selectedRun && workflow.id !== null && (
                            <p className="pointer-events-none absolute top-4 left-1/2 -translate-x-1/2 rounded-full border bg-background px-3 py-1 text-xs text-muted-foreground shadow-sm">
                                {t('Select a run to see the path it took.')}
                            </p>
                        )}
                        {!executions && trigger && nodes.length === 1 && (
                            <p className="pointer-events-none absolute top-4 left-1/2 -translate-x-1/2 rounded-full border bg-background px-3 py-1 text-xs text-muted-foreground shadow-sm">
                                {t(
                                    'Drag steps from the left, or hover a step and press + to add the next one.',
                                )}
                            </p>
                        )}
                    </div>

                    {(selected || panel === 'test') && (
                        <aside className="flex w-full max-w-md shrink-0 flex-col border-l bg-background max-md:absolute max-md:inset-y-0 max-md:right-0 max-md:z-10">
                            {selected ? (
                                <NodeInspector
                                    key={`${selected.id}-${mode}`}
                                    nodeId={selected.id}
                                    type={selected.type as WorkflowNodeType}
                                    data={selected.data}
                                    options={options}
                                    variables={variables}
                                    inLoop={
                                        (context.loopDepths.get(selected.id) ??
                                            0) > 0
                                    }
                                    error={context.errors[selected.id]}
                                    step={path?.nodes.get(selected.id)}
                                    readOnly={executions}
                                    onChange={(data) =>
                                        updateNodeData(selected.id, data)
                                    }
                                    onDelete={() => deleteNode(selected.id)}
                                    onClose={() => setSelectedId(null)}
                                />
                            ) : (
                                <TestPanel
                                    result={testResult}
                                    running={testing}
                                    onRun={runTest}
                                    onClose={() => {
                                        setPanel('inspector');
                                        setTestResult(null);
                                    }}
                                />
                            )}
                        </aside>
                    )}
                </div>
            </div>

            <Dialog
                open={picker !== null}
                onOpenChange={(open) => !open && setPicker(null)}
            >
                <DialogContent className="flex max-h-[80svh] flex-col sm:max-w-md">
                    <DialogTitle>{t('Add a step')}</DialogTitle>
                    <NodePalette
                        onPick={pickAfter}
                        autoFocus
                        draggable={false}
                    />
                </DialogContent>
            </Dialog>

            <Dialog open={settingsOpen} onOpenChange={setSettingsOpen}>
                <DialogContent>
                    <DialogTitle>{t('Workflow settings')}</DialogTitle>
                    <DialogDescription>
                        {t('Saved with the workflow.')}
                    </DialogDescription>
                    <div className="grid gap-4">
                        <TextField
                            id="workflow-description"
                            label={t('Description')}
                            multiline
                            value={meta.description}
                            onChange={(description) => {
                                setMeta({ ...meta, description });
                                markDirty();
                            }}
                            error={errors.description}
                        />
                        <div className="grid gap-2">
                            <Label htmlFor="workflow-max-runs">
                                {t('Runs per ticket')}
                            </Label>
                            <Input
                                id="workflow-max-runs"
                                type="number"
                                min={0}
                                max={100}
                                value={meta.max_runs_per_ticket}
                                onChange={(event) => {
                                    setMeta({
                                        ...meta,
                                        max_runs_per_ticket: Number(
                                            event.target.value,
                                        ),
                                    });
                                    markDirty();
                                }}
                            />
                            <p className="text-xs text-muted-foreground">
                                {t(
                                    'How many times this workflow may run for the same ticket. 0 means no limit (up to 100). Keep 1 unless the workflow should repeat.',
                                )}
                            </p>
                        </div>
                        {trigger?.data.event === 'ticket_idle' && (
                            <label className="flex items-start gap-2 text-sm">
                                <Checkbox
                                    checked={meta.apply_to_existing}
                                    onCheckedChange={(checked) => {
                                        setMeta({
                                            ...meta,
                                            apply_to_existing: checked === true,
                                        });
                                        markDirty();
                                    }}
                                />
                                <span>
                                    {t(
                                        'Also act on tickets created before the workflow was turned on',
                                    )}
                                    <span className="block text-xs text-muted-foreground">
                                        {t(
                                            'Off by default, so turning on a workflow does not suddenly change old tickets.',
                                        )}
                                    </span>
                                </span>
                            </label>
                        )}
                    </div>
                    <DialogFooter className="gap-2 sm:justify-between">
                        {workflow.id !== null ? (
                            <ConfirmAction
                                trigger={
                                    <Button
                                        variant="ghost"
                                        className="text-destructive"
                                    >
                                        {t('Delete workflow')}
                                    </Button>
                                }
                                title={t('Delete “:name”?', {
                                    name: workflow.name,
                                })}
                                description={t(
                                    'Its run history is deleted too. Waiting runs stop.',
                                )}
                                href={destroy.url(workflow.id)}
                            />
                        ) : (
                            <span />
                        )}
                        <Button onClick={() => setSettingsOpen(false)}>
                            {t('Done')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </EditorContext.Provider>
    );
}

export default function WorkflowEditorPage(props: Props) {
    return (
        <ReactFlowProvider>
            <WorkflowEditor {...props} />
        </ReactFlowProvider>
    );
}

WorkflowEditorPage.layout = {
    breadcrumbs: [
        { title: 'Admin center', href: '/admin' },
        { title: 'Workflows', href: index() },
    ],
};
