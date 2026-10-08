import { usePage } from '@inertiajs/react';

/**
 * Broadcast channel names start with the installation's scope (see
 * App\Domain\Support\Broadcasting\Channels), e.g. "staff.tickets" → "kitedesk.staff.tickets".
 */
export function useChannelName() {
    const { broadcastScope } = usePage().props;

    return (channel: string): string =>
        `${broadcastScope ?? 'kitedesk'}.${channel}`;
}
