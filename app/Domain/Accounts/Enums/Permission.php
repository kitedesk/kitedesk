<?php

namespace App\Domain\Accounts\Enums;

/**
 * Everything a role can be allowed to do. Values are the permission names stored by
 * spatie/laravel-permission; run `RoleCatalog::sync()` (from a migration) after adding one.
 */
enum Permission: string
{
    case CreateTickets = 'tickets.create';
    case ReplyToTickets = 'tickets.reply';
    case UpdateTickets = 'tickets.update';
    case MergeTickets = 'tickets.merge';
    case ForwardTickets = 'tickets.forward';
    case DeleteTickets = 'tickets.delete';
    case RunWorkflows = 'tickets.run_workflows';
    case ReceiveAssignments = 'tickets.receive_assignments';
    case UseSecrets = 'tickets.secrets';
    case UseAi = 'tickets.use_ai';
    case ShareViews = 'views.share';
    case ShareCannedResponses = 'canned_responses.share';
    case ViewReports = 'reports.view';
    case ExportReports = 'reports.export';
    case ManageBranding = 'admin.branding';
    case ManageTeam = 'admin.team';
    case ManageTicketSetup = 'admin.ticket_setup';
    case ManageSla = 'admin.sla';
    case ManageEmail = 'admin.email';
    case ManageAutomation = 'admin.automation';
    case ManageHelpCenter = 'admin.help_center';
    case ManageIntegrations = 'admin.integrations';

    public function label(): string
    {
        return match ($this) {
            self::CreateTickets => __('Create tickets'),
            self::ReplyToTickets => __('Reply to customers'),
            self::UpdateTickets => __('Edit ticket properties'),
            self::MergeTickets => __('Merge and link tickets'),
            self::ForwardTickets => __('Forward tickets'),
            self::DeleteTickets => __('Delete tickets'),
            self::RunWorkflows => __('Run workflows'),
            self::ReceiveAssignments => __('Be assigned tickets'),
            self::UseSecrets => __('Request and share secrets'),
            self::UseAi => __('Use the AI assistant'),
            self::ShareViews => __('Share views with the team'),
            self::ShareCannedResponses => __('Share canned responses with the team'),
            self::ViewReports => __('View reports'),
            self::ExportReports => __('Export reports'),
            self::ManageBranding => __('Branding'),
            self::ManageTeam => __('Users, groups, organizations and roles'),
            self::ManageTicketSetup => __('Forms, fields, categories, statuses, ticket numbers and satisfaction survey'),
            self::ManageSla => __('SLA policies and business hours'),
            self::ManageEmail => __('Email and email templates'),
            self::ManageAutomation => __('Routing rules and workflows'),
            self::ManageHelpCenter => __('Help center'),
            self::ManageIntegrations => __('AI assistant, website widget, webhooks and API tokens'),
        };
    }

    /**
     * A hint shown under the checkbox; empty when the label says it all.
     */
    public function description(): string
    {
        return match ($this) {
            self::ReplyToTickets => __('Without it, they can only add internal notes.'),
            self::UpdateTickets => __('Status, priority, assignee, group, tags and fields, also in bulk.'),
            self::DeleteTickets => __('Deleted tickets and their messages cannot be recovered.'),
            self::ReceiveAssignments => __('Appear in the assignee list, receive forwarded and auto-assigned tickets, and get new ticket emails.'),
            self::UseSecrets => __('Exchange passwords and other secrets with customers through encrypted links.'),
            self::UseAi => __('Summarize tickets and draft or improve replies with the AI assistant. Ticket text is sent to the AI provider set up by an admin.'),
            self::ManageTeam => __('Anyone with this permission can change roles, including their own.'),
            default => '',
        };
    }

    /**
     * The heading the permission is listed under on the role form.
     */
    public function group(): string
    {
        return match (true) {
            str_starts_with($this->value, 'tickets.') => __('Tickets'),
            str_starts_with($this->value, 'admin.') => __('Admin center'),
            str_starts_with($this->value, 'reports.') => __('Reports'),
            default => __('Sharing'),
        };
    }

    public function isAdminSection(): bool
    {
        return str_starts_with($this->value, 'admin.');
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
