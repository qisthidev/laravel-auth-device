<?php

namespace Qisthidev\AuthDevice\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Qisthidev\AuthDevice\Models\Invitation;

class InvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected Invitation $invitation;

    /**
     * Create a new notification instance.
     */
    public function __construct(Invitation $invitation)
    {
        $this->invitation = $invitation;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $appName = config('app.name', 'Application');
        $code = $this->invitation->code;
        $expiresAt = $this->invitation->expires_at;

        return (new MailMessage())
            ->subject("You've been invited to {$appName}")
            ->greeting('Hello!')
            ->line("You have been invited to join {$appName}.")
            ->line("Your invitation code is: **{$code}**")
            ->line("This invitation will expire on {$expiresAt->format('F j, Y, g:i a')}.")
            ->action('Accept Invitation', $this->getAcceptUrl())
            ->line('If you did not expect this invitation, you can ignore this email.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'invitation_id' => $this->invitation->id,
            'code' => $this->invitation->code,
            'expires_at' => $this->invitation->expires_at,
        ];
    }

    /**
     * Get the URL to accept the invitation.
     */
    protected function getAcceptUrl(): string
    {
        $prefix = config('auth-device.route_prefix', 'api/auth');

        return url("{$prefix}/invitation/{$this->invitation->code}/accept");
    }
}
