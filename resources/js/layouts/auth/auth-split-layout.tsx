import { Link, usePage } from '@inertiajs/react';
import { m, useReducedMotion } from 'framer-motion';
import { Inbox, Sparkles, Timer } from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
import { KiteDeskLogo, useShowsKiteDeskLogo } from '@/components/kitedesk-logo';
import { BrandLogo, useHasBrandLogo } from '@/components/brand-logo';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

/**
 * Sign-in, sign-up and the other account pages: the form on the left and, on wide screens,
 * a panel in the brand color showing what the help desk does. Pages that aren't about the
 * help desk (the operator console) pass `showcase: false` and get the form alone.
 */
export default function AuthSplitLayout({
    children,
    title,
    description,
    showcase = true,
}: AuthLayoutProps) {
    const { name } = usePage().props;
    // Pages pass English titles; translate them here.
    const { t } = useTranslation();
    const hasLogo = useHasBrandLogo();
    const showsKiteDeskLogo = useShowsKiteDeskLogo();

    return (
        <div
            className={cn(
                'grid min-h-svh bg-background',
                showcase && 'lg:grid-cols-[minmax(0,1fr)_minmax(0,1.1fr)]',
            )}
        >
            <div className="flex flex-col px-6 py-6 sm:px-10 sm:py-8">
                <Link
                    href={home()}
                    className={cn(
                        'flex items-center gap-2.5 self-start font-semibold tracking-tight',
                        !showcase && 'sm:self-center',
                    )}
                >
                    {hasLogo ? (
                        <BrandLogo className="h-8" />
                    ) : showsKiteDeskLogo ? (
                        <KiteDeskLogo className="h-10" />
                    ) : (
                        <>
                            <AppLogoIcon className="size-7 fill-current text-primary" />
                            <span>{name}</span>
                        </>
                    )}
                </Link>

                <main className="flex flex-1 items-center justify-center py-12">
                    <div className="w-full max-w-sm">
                        <div className="mb-8 space-y-2">
                            <h1 className="text-2xl font-semibold tracking-tight">
                                {t(title ?? '')}
                            </h1>
                            {description && (
                                <p className="text-sm text-balance text-muted-foreground">
                                    {t(description)}
                                </p>
                            )}
                        </div>
                        {children}
                    </div>
                </main>

                <p className="text-xs text-muted-foreground">
                    © {new Date().getFullYear()} {name}
                </p>
            </div>

            {showcase && <Showcase />}
        </div>
    );
}

/**
 * A drawn preview of the inbox: a few tickets with their status and SLA clock.
 */
function Showcase() {
    const { t } = useTranslation();
    const reduceMotion = useReducedMotion();

    const tickets = [
        {
            initials: 'MC',
            requester: 'Marina Costa',
            subject: t("Can't sign in after the update"),
            status: t('Open'),
            tone: 'bg-sky-500/15 text-sky-700 dark:text-sky-300',
            due: '12 min',
        },
        {
            initials: 'DP',
            requester: 'Daniel Park',
            subject: t('Invoice for September'),
            status: t('Pending'),
            tone: 'bg-amber-500/15 text-amber-700 dark:text-amber-300',
            due: '2 h',
        },
        {
            initials: 'AT',
            requester: 'Aiko Tanaka',
            subject: t('How do I export my data?'),
            status: t('Solved'),
            tone: 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300',
            due: null,
        },
    ];

    const features = [
        { icon: Inbox, label: t('One inbox for email and web requests') },
        { icon: Timer, label: t('SLAs measured in business hours') },
        { icon: Sparkles, label: t('AI assistance with your own model') },
    ];

    const rise = (delay: number) =>
        reduceMotion
            ? {}
            : {
                  initial: { opacity: 0, y: 12 },
                  animate: { opacity: 1, y: 0 },
                  transition: {
                      duration: 0.5,
                      delay,
                      ease: 'easeOut' as const,
                  },
              };

    return (
        <aside
            aria-hidden
            className="relative m-3 hidden flex-col justify-between overflow-hidden rounded-2xl bg-primary p-12 text-primary-foreground lg:flex"
        >
            <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_1px_1px,currentColor_1px,transparent_0)] [mask-image:linear-gradient(to_bottom,black,transparent_70%)] bg-size-[24px_24px] opacity-15" />
            <div className="pointer-events-none absolute -top-32 -right-32 size-96 rounded-full bg-white/15 blur-3xl" />
            <div className="pointer-events-none absolute -bottom-40 -left-24 size-96 rounded-full bg-black/15 blur-3xl" />

            <m.div {...rise(0)} className="relative max-w-md space-y-4">
                <h2 className="text-3xl font-semibold tracking-tight text-balance xl:text-4xl">
                    {t('Every request, answered on time.')}
                </h2>
                <p className="text-base text-balance opacity-80">
                    {t(
                        'A shared inbox, SLAs your team can see, and a help center your customers can use on their own.',
                    )}
                </p>
            </m.div>

            <div className="relative my-10">
                <m.div
                    {...rise(0.15)}
                    className="max-w-md rounded-xl bg-background p-2 text-foreground shadow-2xl ring-1 ring-black/5"
                >
                    {tickets.map((ticket, index) => (
                        <m.div
                            key={ticket.initials}
                            {...rise(0.3 + index * 0.12)}
                            className="flex items-center gap-3 rounded-lg px-3 py-2.5 odd:bg-muted/60"
                        >
                            <span className="flex size-8 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xs font-semibold text-primary">
                                {ticket.initials}
                            </span>
                            <span className="min-w-0 flex-1">
                                <span className="block truncate text-sm font-medium">
                                    {ticket.subject}
                                </span>
                                <span className="block truncate text-xs text-muted-foreground">
                                    {ticket.requester}
                                </span>
                            </span>
                            {ticket.due && (
                                <span className="hidden items-center gap-1 text-xs text-muted-foreground tabular-nums xl:flex">
                                    <Timer className="size-3.5" />
                                    {ticket.due}
                                </span>
                            )}
                            <span
                                className={cn(
                                    'shrink-0 rounded-full px-2 py-0.5 text-xs font-medium',
                                    ticket.tone,
                                )}
                            >
                                {ticket.status}
                            </span>
                        </m.div>
                    ))}
                </m.div>
            </div>

            <m.ul {...rise(0.6)} className="relative space-y-3 text-sm">
                {features.map((feature) => (
                    <li key={feature.label} className="flex items-center gap-3">
                        <span className="flex size-8 items-center justify-center rounded-lg bg-white/15">
                            <feature.icon className="size-4" />
                        </span>
                        {feature.label}
                    </li>
                ))}
            </m.ul>
        </aside>
    );
}
