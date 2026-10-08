import { useSyncExternalStore } from 'react';

const TICK_MS = 30_000;

const listeners = new Set<() => void>();
let now = Date.now();
let interval: number | undefined;

function subscribe(listener: () => void): () => void {
    listeners.add(listener);

    if (interval === undefined) {
        now = Date.now();
        interval = window.setInterval(() => {
            now = Date.now();
            listeners.forEach((notify) => notify());
        }, TICK_MS);
    }

    return () => {
        listeners.delete(listener);

        if (listeners.size === 0) {
            window.clearInterval(interval);
            interval = undefined;
        }
    };
}

/**
 * The current time, refreshed every 30 seconds by one interval shared by every caller
 * (a ticket list renders dozens of SLA timers).
 */
export function useNow(): number {
    return useSyncExternalStore(
        subscribe,
        () => now,
        () => now,
    );
}
