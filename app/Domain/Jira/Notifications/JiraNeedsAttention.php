<?php

namespace App\Domain\Jira\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the people who manage integrations that the Jira connection stopped working, or that
 * its API token is about to expire.
 */
class JiraNeedsAttention extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $message) {}

    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Jira integration needs attention'))
            ->line($this->message)
            ->action(__('Open Jira settings'), route('admin.jira.edit'));
    }

    /**
     * The inbox shows `message` and opens `url` for notifications of the "generic" kind.
     *
     * @return array<string, mixed>
     */
    public function toArray(User $notifiable): array
    {
        return [
            'kind' => 'generic',
            'message' => $this->message,
            'url' => route('admin.jira.edit', absolute: false),
        ];
    }

    /**
     * The staff who manage integrations.
     *
     * @return list<User>
     */
    public static function recipients(): array
    {
        return array_values(User::query()->staff()->get()
            ->filter(fn (User $user): bool => $user->hasPermission('admin.integrations'))
            ->all());
    }
}
