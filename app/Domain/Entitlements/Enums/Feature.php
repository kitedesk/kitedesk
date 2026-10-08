<?php

namespace App\Domain\Entitlements\Enums;

/**
 * Features a plan can leave out. Checked where they are used; the open-source edition has them all.
 */
enum Feature: string
{
    /** The AI assistant: summaries, drafts, AI workflow steps. */
    case Ai = 'ai';

    /** The MCP server for AI apps. */
    case Mcp = 'mcp';

    /** The REST API. */
    case Api = 'api';

    /** Workflows (routing rules are always included). */
    case Workflows = 'workflows';

    /** Hiding "Powered by KiteDesk" and adding custom CSS to the help center. */
    case CustomBranding = 'custom_branding';

    /** SLA policies and business schedules. */
    case Sla = 'sla';

    /** Roles beyond the built-in ones. Existing custom roles keep working. */
    case CustomRoles = 'custom_roles';

    /** The satisfaction survey sent after a ticket is solved. */
    case Satisfaction = 'satisfaction';

    /** Outgoing webhooks. */
    case Webhooks = 'webhooks';

    /** The reports page and the reports API. */
    case Reports = 'reports';

    /** Changing the wording of the emails KiteDesk sends. Turning one off is always allowed. */
    case CustomEmailTemplates = 'custom_email_templates';

    /** The support widget other websites embed to open tickets. */
    case Widget = 'widget';
}
