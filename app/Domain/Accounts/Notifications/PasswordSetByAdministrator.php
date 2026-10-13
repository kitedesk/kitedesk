<?php

namespace App\Domain\Accounts\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells someone an administrator gave them a new password. The password itself is never sent.
 */
class PasswordSetByAdministrator extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public bool $changeRequired) {}

    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Your password was changed'))
            ->greeting(__('Hi :name,', ['name' => $notifiable->name]))
            ->line(__('An administrator set a new password for your account. Sign in with the password they gave you.'))
            ->when($this->changeRequired, fn (MailMessage $message) => $message->line(__('You will be asked to choose your own password when you sign in.')))
            ->action(__('Sign in'), route('login'))
            ->line(__('If you didn’t expect this, contact your administrator.'));
    }
}
