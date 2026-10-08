import { router } from '@inertiajs/react';

/**
 * Wait this long after the last realtime event before reloading…
 */
const QUIET_MS = 750;

/**
 * …but never longer than this after the first one (a busy helpdesk never goes quiet).
 */
const MAX_WAIT_MS = 3000;

const pending = new Set<string>();
let timer: number | undefined;
let firstQueuedAt = 0;

function flush(): void {
    const only = [...pending];

    pending.clear();
    timer = undefined;
    router.reload({ only });
}

/**
 * Queue a partial reload for realtime events. A burst of events (a bulk edit, several
 * listeners on one page) becomes one request for the union of the props asked for.
 */
export function scheduleReload(only: string[]): void {
    const now = Date.now();

    if (timer === undefined) {
        firstQueuedAt = now;
    }

    only.forEach((prop) => pending.add(prop));
    window.clearTimeout(timer);
    timer = window.setTimeout(
        flush,
        Math.max(0, Math.min(QUIET_MS, firstQueuedAt + MAX_WAIT_MS - now)),
    );
}
