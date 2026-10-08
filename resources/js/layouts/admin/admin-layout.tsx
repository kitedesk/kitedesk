import { Link, usePage } from '@inertiajs/react';
import {
    BookOpen,
    Building2,
    CircleDot,
    FileText,
    FolderTree,
    Gauge,
    Hash,
    KeyRound,
    Mail,
    MailCheck,
    Palette,
    Route,
    ShieldCheck,
    Sparkles,
    Star,
    Timer,
    Users,
    UsersRound,
    Webhook,
    Workflow,
} from 'lucide-react';
import type { PropsWithChildren } from 'react';
import { useEntitlements } from '@/hooks/use-entitlements';
import { usePermissions } from '@/hooks/use-permissions';
import { useTranslation } from '@/hooks/use-translation';
import { registeredAdminNav } from '@/lib/extensions';
import type { AdminNavItem } from '@/lib/extensions';
import { cn } from '@/lib/utils';

/**
 * Admin center navigation, grouped into sections. URLs are plain strings so each area can
 * ship independently. Titles and descriptions are English keys, translated where they are rendered.
 */
export const adminNavSections: { title: string; items: AdminNavItem[] }[] = [
    {
        title: 'General',
        items: [
            {
                title: 'Overview',
                href: '/admin',
                icon: Gauge,
                description: 'Admin center home',
            },
            {
                title: 'Branding',
                href: '/admin/branding',
                permission: 'admin.branding',
                icon: Palette,
                description: 'Name, logo, colors and emails',
            },
        ],
    },
    {
        title: 'People',
        items: [
            {
                title: 'Users',
                href: '/admin/users',
                permission: 'admin.team',
                icon: Users,
                description: 'Agents, admins and customers',
            },
            {
                title: 'Roles',
                href: '/admin/roles',
                permission: 'admin.team',
                icon: ShieldCheck,
                description:
                    'What each team member may do and which tickets they see',
            },
            {
                title: 'Groups',
                href: '/admin/groups',
                permission: 'admin.team',
                icon: UsersRound,
                description: 'Route tickets to the right team',
            },
            {
                title: 'Organizations',
                href: '/admin/organizations',
                permission: 'admin.team',
                icon: Building2,
                description: 'Customer companies and email domains',
            },
        ],
    },
    {
        title: 'Tickets',
        items: [
            {
                title: 'Forms & fields',
                href: '/admin/ticket-forms',
                permission: 'admin.ticket_setup',
                icon: FileText,
                description:
                    'Custom fields, and which ones each kind of request asks for',
                includes: ['/admin/ticket-fields'],
            },
            {
                title: 'Categories',
                href: '/admin/ticket-categories',
                permission: 'admin.ticket_setup',
                icon: FolderTree,
                description: 'Categories and subcategories that pick the form',
            },
            {
                title: 'Statuses',
                href: '/admin/ticket-statuses',
                permission: 'admin.ticket_setup',
                icon: CircleDot,
                description: 'Your own statuses, used as board lanes',
            },
            {
                title: 'Ticket numbers',
                href: '/admin/ticket-numbers',
                permission: 'admin.ticket_setup',
                icon: Hash,
                description: 'How new tickets are numbered',
            },
            {
                title: 'SLA policies',
                href: '/admin/sla-policies',
                feature: 'sla',
                permission: 'admin.sla',
                icon: Timer,
                description: 'Response and resolution targets',
            },
            {
                title: 'Satisfaction survey',
                href: '/admin/satisfaction',
                feature: 'satisfaction',
                permission: 'admin.ticket_setup',
                icon: Star,
                description: 'Ask customers to rate solved tickets',
            },
        ],
    },
    {
        title: 'Email',
        items: [
            {
                title: 'Email',
                href: '/admin/mailboxes',
                permission: 'admin.email',
                icon: Mail,
                description: 'Support addresses that turn email into tickets',
            },
            {
                title: 'Email templates',
                href: '/admin/email-templates',
                permission: 'admin.email',
                icon: MailCheck,
                description: 'Auto-replies and notification emails',
            },
        ],
    },
    {
        title: 'Automation',
        items: [
            {
                title: 'Routing rules',
                href: '/admin/routing-rules',
                permission: 'admin.automation',
                icon: Route,
                description:
                    'Send new tickets to the right group automatically',
            },
            {
                title: 'Workflows',
                href: '/admin/workflows',
                feature: 'workflows',
                permission: 'admin.automation',
                icon: Workflow,
                description:
                    'Automate replies, assignments, follow-ups and more',
            },
        ],
    },
    {
        title: 'Help center & integrations',
        items: [
            {
                title: 'Help center',
                href: '/admin/knowledge-base',
                permission: 'admin.help_center',
                icon: BookOpen,
                description: 'Categories, sections and articles',
            },
            {
                title: 'AI assistant',
                href: '/admin/ai',
                feature: 'ai',
                permission: 'admin.integrations',
                icon: Sparkles,
                description: 'AI drafts, summaries and the MCP server',
            },
            {
                title: 'Webhooks',
                href: '/admin/webhooks',
                feature: 'webhooks',
                permission: 'admin.integrations',
                icon: Webhook,
                description: 'Notify other systems about ticket events',
            },
            {
                title: 'API tokens',
                href: '/admin/api-tokens',
                feature: 'api',
                permission: 'admin.integrations',
                icon: KeyRound,
                description: 'Access the REST API',
            },
        ],
    },
];

/**
 * The core sections plus items registered by KiteDesk packages.
 */
function allAdminNavSections() {
    const sections = adminNavSections.map((section) => ({
        ...section,
        items: [...section.items],
    }));

    for (const { section, item } of registeredAdminNav()) {
        const existing = sections.find((entry) => entry.title === section);

        if (existing) {
            existing.items.push(item);
        } else {
            sections.push({ title: section, items: [item] });
        }
    }

    return sections;
}

/**
 * The admin sections the signed-in user's role grants, without empty groups.
 */
export function useAdminNavSections() {
    const { can } = usePermissions();
    const { includes } = useEntitlements();

    return allAdminNavSections()
        .map((section) => ({
            ...section,
            items: section.items.filter(
                (item) =>
                    (!item.permission || can(item.permission)) &&
                    (!item.feature || includes(item.feature)),
            ),
        }))
        .filter((section) => section.items.length > 0);
}

export default function AdminLayout({ children }: PropsWithChildren) {
    const path = usePage().url.split('?')[0];
    const { t } = useTranslation();
    const sections = useAdminNavSections();

    const isActive = (item: AdminNavItem) =>
        item.href === '/admin'
            ? path === '/admin'
            : [item.href, ...(item.includes ?? [])].some(
                  (href) => path === href || path.startsWith(`${href}/`),
              );

    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:flex-row md:p-6">
            <aside className="md:w-52 md:shrink-0">
                <p className="mb-2 px-2 text-xs font-medium tracking-wide text-muted-foreground uppercase">
                    {t('Admin center')}
                </p>
                <nav
                    className="flex gap-1 overflow-x-auto md:flex-col md:gap-4"
                    aria-label={t('Admin center')}
                >
                    {sections.map((section) => (
                        <div
                            key={section.title}
                            className="flex shrink-0 gap-1 md:flex-col"
                        >
                            <p className="hidden px-2 text-[0.7rem] font-medium tracking-wide text-muted-foreground/70 uppercase md:block">
                                {t(section.title)}
                            </p>
                            {section.items.map((item) => (
                                <Link
                                    key={item.href}
                                    href={item.href}
                                    prefetch
                                    className={cn(
                                        'flex shrink-0 items-center gap-2 rounded-md px-2 py-1.5 text-sm text-muted-foreground transition-colors hover:bg-accent hover:text-foreground',
                                        isActive(item) &&
                                            'bg-accent font-medium text-foreground',
                                    )}
                                >
                                    <item.icon className="size-4" />
                                    {t(item.title)}
                                </Link>
                            ))}
                        </div>
                    ))}
                </nav>
            </aside>
            <div className="min-w-0 flex-1">{children}</div>
        </div>
    );
}
