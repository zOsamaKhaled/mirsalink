<?php

namespace App\Notifications;

use App\Actions\Team\SendInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The email that brings a new member in: who invited them, and the link
 * where they choose their password. Written in the inviting supervisor's
 * language.
 */
class TeamInvitation extends Notification
{
    use Queueable;

    public function __construct(
        public string $link,
        public ?string $inviterName = null,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $app = __('app.brand.name');

        return (new MailMessage)
            ->subject(__('app.team.invitation_mail.subject', ['app' => $app]))
            ->greeting(__('app.team.invitation_mail.greeting', ['name' => $notifiable->name]))
            ->line($this->inviterName === null
                ? __('app.team.invitation_mail.body_anonymous', ['app' => $app])
                : __('app.team.invitation_mail.body', ['inviter' => $this->inviterName, 'app' => $app]))
            ->action(__('app.team.invitation_mail.action'), $this->link)
            ->line(__('app.team.invitation_mail.expires', ['days' => (string) SendInvitation::DAYS_VALID]))
            ->salutation(__('app.team.invitation_mail.salutation', ['app' => $app]));
    }
}
