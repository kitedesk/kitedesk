import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/hooks/use-initials';
import { cn } from '@/lib/utils';

const palette = [
    'bg-rose-500/15 text-rose-700 dark:text-rose-300',
    'bg-amber-500/15 text-amber-700 dark:text-amber-300',
    'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300',
    'bg-sky-500/15 text-sky-700 dark:text-sky-300',
    'bg-violet-500/15 text-violet-700 dark:text-violet-300',
    'bg-fuchsia-500/15 text-fuchsia-700 dark:text-fuchsia-300',
];

/**
 * The profile photo, or initials with a stable color derived from the name.
 */
export function UserAvatar({
    name,
    src,
    className,
}: {
    name: string;
    src?: string | null;
    className?: string;
}) {
    const getInitials = useInitials();
    const hash = Array.from(name).reduce(
        (sum, char) => sum + char.charCodeAt(0),
        0,
    );

    return (
        <Avatar className={cn('size-8', className)}>
            {src && (
                <AvatarImage src={src} alt={name} className="object-cover" />
            )}
            <AvatarFallback
                className={cn(
                    'text-xs font-semibold',
                    palette[hash % palette.length],
                )}
            >
                {getInitials(name)}
            </AvatarFallback>
        </Avatar>
    );
}
