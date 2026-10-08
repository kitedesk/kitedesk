import { Link, router } from '@inertiajs/react';
import { Coffee, LogOut, Settings } from 'lucide-react';
import {
    DropdownMenuCheckboxItem,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import { UserInfo } from '@/components/user-info';
import { useMobileNavigation } from '@/hooks/use-mobile-navigation';
import { usePermissions } from '@/hooks/use-permissions';
import { useTranslation } from '@/hooks/use-translation';
import { logout } from '@/routes';
import { availability } from '@/routes/agent';
import { edit } from '@/routes/profile';
import type { User } from '@/types';

type Props = {
    user: User;
};

export function UserMenuContent({ user }: Props) {
    const cleanup = useMobileNavigation();
    const { t } = useTranslation();
    const { can } = usePermissions();
    // Only people who can be assigned tickets get them from auto-assignment, so "away" means nothing to others.
    const canBeAway = can('tickets.receive_assignments');

    const handleLogout = () => {
        cleanup();
        router.flushAll();
    };

    return (
        <>
            <DropdownMenuLabel className="p-0 font-normal">
                <div className="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
                    <UserInfo user={user} showEmail={true} />
                </div>
            </DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuGroup>
                {canBeAway && (
                    <DropdownMenuCheckboxItem
                        checked={user.is_available === false}
                        onCheckedChange={() =>
                            router.patch(
                                availability.url(),
                                {},
                                { preserveScroll: true },
                            )
                        }
                    >
                        <Coffee className="mr-2" />
                        {t('Away')}
                    </DropdownMenuCheckboxItem>
                )}
                <DropdownMenuItem asChild>
                    <Link
                        className="block w-full cursor-pointer"
                        href={edit()}
                        prefetch
                        onClick={cleanup}
                    >
                        <Settings className="mr-2" />
                        {t('Settings')}
                    </Link>
                </DropdownMenuItem>
            </DropdownMenuGroup>
            <DropdownMenuSeparator />
            <DropdownMenuItem asChild>
                <Link
                    className="block w-full cursor-pointer"
                    href={logout()}
                    as="button"
                    onClick={handleLogout}
                    data-test="logout-button"
                >
                    <LogOut className="mr-2" />
                    {t('Log out')}
                </Link>
            </DropdownMenuItem>
        </>
    );
}
