import { usePage } from '@inertiajs/react';
import { create as guestCreate } from '@/routes/guest/tickets';
import { create as portalCreate } from '@/routes/portal/tickets';

/**
 * Where "Submit a request" leads: the portal form when signed in, otherwise the guest
 * form when it is enabled (and the portal form, which asks to sign in, when it isn't).
 */
export function useNewRequestUrl() {
    const { auth, guestTickets } = usePage().props;

    return !auth.user && guestTickets ? guestCreate() : portalCreate();
}
