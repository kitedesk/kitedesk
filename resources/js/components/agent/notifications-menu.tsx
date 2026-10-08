import { router, usePage } from '@inertiajs/react';
import { useCallback, useEffect, useState } from 'react';
import { NotificationBell } from '@/components/ui/notification-bell';
import type { Notification } from '@/components/ui/notification-bell';
import { useTranslation } from '@/hooks/use-translation';
import { t } from '@/lib/i18n';
import {
    index as notificationsIndex,
    read,
} from '@/routes/agent/notifications';
import { show } from '@/routes/agent/tickets';

type ServerNotification = {
    id: string;
    data: {
        kind:
            | 'ticket_replied'
            | 'ticket_assigned'
            | 'ticket_mentioned'
            | 'ticket_forwarded'
            | 'sla_breached'
            | 'workflow_alert'
            | 'secret_submitted';
        ticket_id: number;
        ticket_number?: string;
        subject: string;
        author?: string | null;
        excerpt?: string;
        message?: string;
        /** Group a ticket was forwarded to. */
        group?: string | null;
    };
    read_at: string | null;
    created_at: string;
};

function describe(notification: ServerNotification): string {
    const {
        kind,
        ticket_id: id,
        ticket_number,
        subject,
        author,
        message,
        group,
    } = notification.data;
    // Notifications stored before ticket numbers existed only carry the id.
    const number = ticket_number ?? `#${id}`;

    switch (kind) {
        case 'ticket_assigned':
            return t(':author assigned you :number :subject', {
                author: author ?? t('Someone'),
                number,
                subject,
            });
        case 'ticket_forwarded':
            return group
                ? t(':author forwarded :number :subject to :group', {
                      author: author ?? t('Someone'),
                      number,
                      subject,
                      group,
                  })
                : t(':author forwarded you :number :subject', {
                      author: author ?? t('Someone'),
                      number,
                      subject,
                  });
        case 'ticket_mentioned':
            return t(':author mentioned you on :number :subject', {
                author: author ?? t('Someone'),
                number,
                subject,
            });
        case 'sla_breached':
            return t('SLA breached on :number :subject', { number, subject });
        case 'workflow_alert':
            return `${number} · ${message ?? subject}`;
        case 'secret_submitted':
            return t('Secret received on :number: :label', {
                number,
                label: message ?? subject,
            });
        default:
            return t(':author replied on :number :subject', {
                author: author ?? t('Customer'),
                number,
                subject,
            });
    }
}

/**
 * Agent notification inbox built on EasyUI's NotificationBell.
 */
export function NotificationsMenu() {
    const { agentNav } = usePage().props;
    useTranslation();
    const [isOpen, setIsOpen] = useState(false);
    const [items, setItems] = useState<ServerNotification[]>([]);

    const load = useCallback(async () => {
        const response = await fetch(notificationsIndex.url(), {
            headers: { Accept: 'application/json' },
        });

        if (response.ok) {
            const payload = (await response.json()) as {
                notifications: ServerNotification[];
            };
            setItems(payload.notifications);
        }
    }, []);

    // The list is only fetched while the menu is open; the badge uses the shared count.
    useEffect(() => {
        if (isOpen) {
            void load();
        }
    }, [isOpen, load, agentNav?.unreadNotifications]);

    const markAllRead = () => {
        setItems((current) =>
            current.map((item) => ({
                ...item,
                read_at: item.read_at ?? new Date().toISOString(),
            })),
        );
        router.post(read(), {}, { preserveScroll: true, preserveState: true });
    };

    const notifications: Notification[] = items.map((item) => ({
        id: item.id,
        message: describe(item),
        timestamp: item.created_at,
        read: item.read_at !== null,
        action: () => {
            setIsOpen(false);
            router.visit(show(item.data.ticket_id));
        },
    }));

    return (
        <NotificationBell
            notifications={notifications}
            isOpen={isOpen}
            onOpenChange={setIsOpen}
            unreadCount={
                isOpen ? undefined : (agentNav?.unreadNotifications ?? 0)
            }
            onMarkAsRead={() => markAllRead()}
            onMarkAllAsRead={markAllRead}
        />
    );
}
