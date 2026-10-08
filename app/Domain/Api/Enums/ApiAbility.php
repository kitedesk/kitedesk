<?php

namespace App\Domain\Api\Enums;

use App\Domain\Accounts\Enums\Permission;
use App\Models\User;

/**
 * What a REST API token may be used for. A token never does more than its owner: each ability
 * needs the owner's role to hold its permissions, checked when the token is created and again
 * on every request. Policies still decide record by record (e.g. which tickets the owner sees).
 */
enum ApiAbility: string
{
    case TicketsRead = 'tickets:read';
    case TicketsWrite = 'tickets:write';
    case TicketsMerge = 'tickets:merge';
    case TicketsDelete = 'tickets:delete';
    case UsersRead = 'users:read';
    case UsersWrite = 'users:write';
    case SetupWrite = 'setup:write';
    case KbRead = 'kb:read';
    case KbWrite = 'kb:write';
    case WebhooksManage = 'webhooks:manage';
    case ReportsRead = 'reports:read';

    public function label(): string
    {
        return match ($this) {
            self::TicketsRead => __('Read tickets, their messages and attachments, and ticket setup such as groups, statuses and fields'),
            self::TicketsWrite => __('Create and update tickets, add replies and notes, CCs, tags and links'),
            self::TicketsMerge => __('Merge tickets'),
            self::TicketsDelete => __('Delete tickets'),
            self::UsersRead => __('Read users, organizations, groups and roles'),
            self::UsersWrite => __('Create, update and deactivate users, and manage organizations and groups'),
            self::SetupWrite => __('Manage categories, fields, forms and statuses'),
            self::KbRead => __('Read help center articles'),
            self::KbWrite => __('Write and publish help center articles'),
            self::WebhooksManage => __('Manage webhooks'),
            self::ReportsRead => __('Read report numbers'),
        };
    }

    /**
     * Permissions the token owner's role must hold. Ticket writes are checked per action by the
     * ticket policy (create, edit, reply), so a light agent's token can still add notes.
     *
     * @return list<Permission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::TicketsMerge => [Permission::MergeTickets],
            self::TicketsDelete => [Permission::DeleteTickets],
            self::UsersWrite => [Permission::ManageTeam],
            self::SetupWrite => [Permission::ManageTicketSetup],
            self::KbWrite => [Permission::ManageHelpCenter],
            self::WebhooksManage => [Permission::ManageIntegrations],
            self::ReportsRead => [Permission::ViewReports],
            default => [],
        };
    }

    /**
     * Whether a token owned by this user may use the ability: active staff holding its permissions.
     */
    public function allowedFor(User $user): bool
    {
        return $user->isStaff()
            && ! $user->isDeactivated()
            && collect($this->permissions())->every(fn (Permission $permission): bool => $user->hasPermission($permission));
    }

    /**
     * @return list<self>
     */
    public static function availableTo(User $user): array
    {
        return array_values(array_filter(self::cases(), fn (self $ability): bool => $ability->allowedFor($user)));
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $ability): array => ['value' => $ability->value, 'label' => $ability->label()], self::cases());
    }
}
