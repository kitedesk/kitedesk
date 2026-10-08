import { Copy, Eye, Plus, Settings2, Trash2, Unlink } from 'lucide-react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuShortcut,
    DropdownMenuSub,
    DropdownMenuSubContent,
    DropdownMenuSubTrigger,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { FlowNode } from '@/components/workflows/graph-utils';
import type { NodeOutput } from '@/components/workflows/node-catalog';
import { NODE_DEFINITIONS } from '@/components/workflows/node-catalog';
import { useTranslation } from '@/hooks/use-translation';
import type { WorkflowNodeType } from '@/types';

export type ContextMenuPosition = { nodeId: string; x: number; y: number };

/**
 * Right-click menu for a step on the canvas. Positioned at the pointer, relative to the canvas.
 */
export function NodeContextMenu({
    position,
    node,
    connectedOutputs,
    hasConnections,
    readOnly,
    onClose,
    onOpen,
    onAddAfter,
    onDuplicate,
    onDisconnect,
    onDelete,
}: {
    position: ContextMenuPosition | null;
    node: FlowNode | undefined;
    connectedOutputs: Set<string>;
    hasConnections: boolean;
    readOnly: boolean;
    onClose: () => void;
    onOpen: (nodeId: string) => void;
    onAddAfter: (nodeId: string, output: NodeOutput) => void;
    onDuplicate: (nodeId: string) => void;
    onDisconnect: (nodeId: string) => void;
    onDelete: (nodeId: string) => void;
}) {
    const { t } = useTranslation();

    if (!position || !node) {
        return null;
    }

    const definition = NODE_DEFINITIONS[node.type as WorkflowNodeType];
    const isTrigger = node.type === 'trigger';
    const freeOutputs = definition
        .outputs(node.data)
        .filter((output) => !connectedOutputs.has(`${node.id}:${output.id}`));
    const outputLabel = (output: NodeOutput) =>
        output.label ? t(output.label) : t('Next step');

    return (
        <DropdownMenu
            open
            onOpenChange={(open) => !open && onClose()}
            modal={false}
        >
            <DropdownMenuTrigger asChild>
                <span
                    aria-hidden
                    className="pointer-events-none absolute size-0"
                    style={{ left: position.x, top: position.y }}
                />
            </DropdownMenuTrigger>
            <DropdownMenuContent
                align="start"
                className="w-56"
                onCloseAutoFocus={(event) => event.preventDefault()}
            >
                <DropdownMenuLabel className="truncate text-xs text-muted-foreground">
                    {t(definition.label)}
                </DropdownMenuLabel>
                <DropdownMenuItem onSelect={() => onOpen(node.id)}>
                    {readOnly ? <Eye /> : <Settings2 />}
                    {readOnly ? t('Show result') : t('Open settings')}
                </DropdownMenuItem>

                {!readOnly && (
                    <>
                        {freeOutputs.length === 1 && (
                            <DropdownMenuItem
                                onSelect={() =>
                                    onAddAfter(node.id, freeOutputs[0])
                                }
                            >
                                <Plus /> {t('Add step after')}
                            </DropdownMenuItem>
                        )}
                        {freeOutputs.length > 1 && (
                            <DropdownMenuSub>
                                <DropdownMenuSubTrigger>
                                    <Plus /> {t('Add step after')}
                                </DropdownMenuSubTrigger>
                                <DropdownMenuSubContent>
                                    {freeOutputs.map((output) => (
                                        <DropdownMenuItem
                                            key={output.id}
                                            onSelect={() =>
                                                onAddAfter(node.id, output)
                                            }
                                        >
                                            {outputLabel(output)}
                                        </DropdownMenuItem>
                                    ))}
                                </DropdownMenuSubContent>
                            </DropdownMenuSub>
                        )}
                        {!isTrigger && (
                            <DropdownMenuItem
                                onSelect={() => onDuplicate(node.id)}
                            >
                                <Copy /> {t('Duplicate')}
                            </DropdownMenuItem>
                        )}
                        <DropdownMenuItem
                            disabled={!hasConnections}
                            onSelect={() => onDisconnect(node.id)}
                        >
                            <Unlink /> {t('Remove connections')}
                        </DropdownMenuItem>
                        {!isTrigger && (
                            <>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem
                                    variant="destructive"
                                    onSelect={() => onDelete(node.id)}
                                >
                                    <Trash2 /> {t('Delete step')}
                                    <DropdownMenuShortcut>
                                        ⌫
                                    </DropdownMenuShortcut>
                                </DropdownMenuItem>
                            </>
                        )}
                    </>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
