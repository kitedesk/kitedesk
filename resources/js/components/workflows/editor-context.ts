import { createContext, useContext } from 'react';
import type { NodeOutput } from '@/components/workflows/node-catalog';
import type { WorkflowOptions, WorkflowStep } from '@/types';

export type EditorContextValue = {
    options: WorkflowOptions;
    /** Validation errors by node id. */
    errors: Record<string, string>;
    /** Steps of the run or test shown on the canvas, by node id. */
    steps: Map<string, WorkflowStep> | null;
    loopDepths: Map<string, number>;
    readOnly: boolean;
    /** Add a step connected to the given output (the "+" on an unconnected output). */
    onAddAfter: (nodeId: string, output: NodeOutput) => void;
    connectedOutputs: Set<string>;
};

export const EditorContext = createContext<EditorContextValue | null>(null);

export function useEditor(): EditorContextValue {
    const value = useContext(EditorContext);

    if (!value) {
        throw new Error('useEditor must be used inside the workflow editor.');
    }

    return value;
}
