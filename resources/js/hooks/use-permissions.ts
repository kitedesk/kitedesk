import { usePage } from '@inertiajs/react';
import type { Permission } from '@/types';

/**
 * What the signed-in user's role allows, for hiding what they can't use. The server still
 * checks every action.
 */
export function usePermissions() {
    const { auth } = usePage().props;
    const can = (permission: Permission): boolean =>
        auth.can?.[permission] === true;
    const canAccessAdmin = Object.entries(auth.can ?? {}).some(
        ([permission, granted]) => permission.startsWith('admin.') && granted,
    );

    return { can, canAccessAdmin };
}
