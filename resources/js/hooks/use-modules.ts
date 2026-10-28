import { usePage } from '@inertiajs/react';

/**
 * The modules an admin has switched on (see App\Domain\Support\Extensions\ModuleRegistry).
 * Anything a module adds to the interface should be hidden while it is off; the server
 * answers 404 for its routes either way.
 */
export function useModules() {
    const { modules } = usePage().props;
    const isEnabled = (module: string): boolean =>
        (modules ?? []).includes(module);

    return { isEnabled, enabled: modules ?? [] };
}
