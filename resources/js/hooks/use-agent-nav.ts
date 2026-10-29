import { useEntitlements } from '@/hooks/use-entitlements';
import { useModules } from '@/hooks/use-modules';
import { usePermissions } from '@/hooks/use-permissions';
import { isAgentNavGroup, registeredAgentNav } from '@/lib/extensions';
import type {
    AgentNavChild,
    AgentNavEntry,
    AgentNavGroup,
    AgentNavItem,
} from '@/lib/extensions';

type Rules = Pick<AgentNavItem, 'permission' | 'feature' | 'module'>;

/**
 * What modules added to the agent sidebar that this person may see now: the entries as
 * registered (`entries`, groups kept) and the same pages as one flat list (`flat`).
 */
export function useAgentNav(): {
    entries: AgentNavEntry[];
    flat: AgentNavItem[];
} {
    const { can } = usePermissions();
    const { includes } = useEntitlements();
    const { isEnabled } = useModules();

    const allowed = (rules: Rules): boolean =>
        (!rules.permission || can(rules.permission)) &&
        (!rules.feature || includes(rules.feature)) &&
        (!rules.module || isEnabled(rules.module));

    const entries: AgentNavEntry[] = [];

    for (const entry of registeredAgentNav()) {
        if (!isAgentNavGroup(entry)) {
            if (allowed(entry)) {
                entries.push(entry);
            }

            continue;
        }

        if (!allowed(entry)) {
            continue;
        }

        const children = entry.children
            .map((child): AgentNavChild => ({
                ...child,
                module: child.module ?? entry.module,
                feature: child.feature ?? entry.feature,
            }))
            .filter(allowed);

        if (children.length > 0) {
            entries.push({ ...entry, children } satisfies AgentNavGroup);
        }
    }

    const flat = entries.flatMap((entry): AgentNavItem[] =>
        isAgentNavGroup(entry)
            ? entry.children.map((child) => ({
                  ...child,
                  icon: child.icon ?? entry.icon,
              }))
            : [entry],
    );

    return { entries, flat };
}
