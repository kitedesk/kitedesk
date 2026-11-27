import { SquareKanban } from 'lucide-react';
import { registerAdminNav, registerTicketPanel } from '@/lib/extensions';
import { JiraPanel } from './components/jira-panel';

registerAdminNav('Help center & integrations', {
    title: 'Jira',
    href: '/admin/jira',
    icon: SquareKanban,
    description: 'Create and link Jira issues from tickets',
    permission: 'admin.integrations',
    module: 'jira',
});

registerTicketPanel(JiraPanel, { module: 'jira' });
