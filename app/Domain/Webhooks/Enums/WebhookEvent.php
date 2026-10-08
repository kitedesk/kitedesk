<?php

namespace App\Domain\Webhooks\Enums;

enum WebhookEvent: string
{
    case TicketCreated = 'ticket.created';
    case TicketUpdated = 'ticket.updated';
    case TicketSolved = 'ticket.solved';
    case TicketMerged = 'ticket.merged';
    case TicketDeleted = 'ticket.deleted';
    case MessageCreated = 'message.created';
    case UserCreated = 'user.created';
    case UserUpdated = 'user.updated';
    case OrganizationCreated = 'organization.created';
    case OrganizationUpdated = 'organization.updated';
    case ArticlePublished = 'article.published';

    public function label(): string
    {
        return match ($this) {
            self::TicketCreated => __('Ticket created'),
            self::TicketUpdated => __('Ticket updated'),
            self::TicketSolved => __('Ticket solved'),
            self::TicketMerged => __('Ticket merged into another'),
            self::TicketDeleted => __('Ticket deleted'),
            self::MessageCreated => __('Message added'),
            self::UserCreated => __('User created'),
            self::UserUpdated => __('User updated'),
            self::OrganizationCreated => __('Organization created'),
            self::OrganizationUpdated => __('Organization updated'),
            self::ArticlePublished => __('Help center article published'),
        };
    }
}
