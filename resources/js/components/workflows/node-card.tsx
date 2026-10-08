import { Handle, Position } from '@xyflow/react';
import type { NodeProps } from '@xyflow/react';
import { AlertCircle, Plus, Repeat } from 'lucide-react';
import { useEditor } from '@/components/workflows/editor-context';
import type { FlowNode } from '@/components/workflows/graph-utils';
import {
    CATEGORY_STYLES,
    NODE_DEFINITIONS,
    STATUS_STYLES,
} from '@/components/workflows/node-catalog';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';

const OUTPUT_STYLES: Record<string, string> = {
    true: 'text-emerald-600 dark:text-emerald-400',
    replied: 'text-emerald-600 dark:text-emerald-400',
    each: 'text-violet-600 dark:text-violet-400',
    false: 'text-rose-600 dark:text-rose-400',
    timeout: 'text-rose-600 dark:text-rose-400',
};

/**
 * A step on the canvas: icon, name, a summary of its settings and its outputs.
 */
export function NodeCard({ id, type, data, selected }: NodeProps<FlowNode>) {
    const { t } = useTranslation();
    const editor = useEditor();
    const definition = NODE_DEFINITIONS[type];
    const outputs = definition.outputs(data);
    const error = editor.errors[id];
    const step = editor.steps?.get(id);
    const inLoop = (editor.loopDepths.get(id) ?? 0) > 0;
    const Icon = definition.icon;
    const dimmed = editor.steps !== null && !step;

    return (
        <div
            className={cn(
                'group relative w-64 rounded-xl border bg-card text-card-foreground shadow-sm transition-[box-shadow,opacity]',
                selected && 'border-primary shadow-md',
                error && 'border-destructive',
                inLoop && 'border-l-4 border-l-violet-500/60',
                step && STATUS_STYLES[step.status],
                dimmed && 'opacity-45',
            )}
        >
            {type !== 'trigger' && (
                <Handle
                    type="target"
                    position={Position.Top}
                    className="!size-3 !border-2 !border-background !bg-muted-foreground"
                />
            )}

            <div className="flex items-start gap-3 p-3">
                <span
                    className={cn(
                        'flex size-9 shrink-0 items-center justify-center rounded-lg',
                        CATEGORY_STYLES[definition.category],
                    )}
                >
                    <Icon className="size-4.5" />
                </span>
                <div className="min-w-0 flex-1">
                    <p className="flex items-center gap-1.5 text-sm font-medium">
                        {t(definition.label)}
                        {inLoop && (
                            <Repeat
                                className="size-3 text-violet-500"
                                aria-label={t('Inside a loop')}
                            />
                        )}
                    </p>
                    <p className="line-clamp-2 text-xs break-words text-muted-foreground">
                        {definition.summary(data, editor.options)}
                    </p>
                </div>
            </div>

            {error && (
                <p className="flex items-start gap-1.5 border-t px-3 py-2 text-xs text-destructive">
                    <AlertCircle className="mt-px size-3.5 shrink-0" />
                    {error}
                </p>
            )}

            {step && (step.iteration !== null || step.error) && (
                <p className="border-t px-3 py-1.5 text-[11px] text-muted-foreground">
                    {step.error ??
                        t('Last iteration: :count', {
                            count: step.iteration ?? 0,
                        })}
                </p>
            )}

            {outputs.length > 0 && (
                <div
                    className={cn(
                        'flex border-t',
                        outputs.length === 1 && 'border-t-0',
                    )}
                >
                    {outputs.map((output) => {
                        const connected = editor.connectedOutputs.has(
                            `${id}:${output.id}`,
                        );

                        return (
                            <div
                                key={output.id}
                                className="relative flex flex-1 justify-center pb-2"
                            >
                                {output.label && (
                                    <span
                                        className={cn(
                                            'pt-1.5 text-[11px] font-medium',
                                            OUTPUT_STYLES[output.id] ??
                                                'text-muted-foreground',
                                        )}
                                    >
                                        {t(output.label)}
                                    </span>
                                )}
                                <Handle
                                    type="source"
                                    id={output.id}
                                    position={Position.Bottom}
                                    className="!size-3 !border-2 !border-background !bg-primary"
                                />
                                {!connected && !editor.readOnly && (
                                    <button
                                        type="button"
                                        title={t('Add a step')}
                                        onClick={(event) => {
                                            event.stopPropagation();
                                            editor.onAddAfter(id, output);
                                        }}
                                        className="nodrag absolute -bottom-9 left-1/2 flex size-6 -translate-x-1/2 items-center justify-center rounded-full border bg-background text-muted-foreground opacity-0 shadow-sm transition-opacity group-hover:opacity-100 hover:text-foreground focus-visible:opacity-100"
                                    >
                                        <Plus className="size-3.5" />
                                    </button>
                                )}
                            </div>
                        );
                    })}
                </div>
            )}
        </div>
    );
}
