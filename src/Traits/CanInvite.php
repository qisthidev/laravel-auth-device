<?php

namespace Qisthidev\AuthDevice\Traits;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\AnonymousNotifiable;
use Qisthidev\AuthDevice\Events\InvitationCreated;
use Qisthidev\AuthDevice\Models\Invitation;
use Qisthidev\AuthDevice\Notifications\InvitationNotification;

trait CanInvite
{
    /**
     * Get all invitations created by this user.
     */
    public function invitations(): HasMany
    {
        $invitationModel = config('auth-device.models.invitation', Invitation::class);

        return $this->hasMany($invitationModel, 'invited_by');
    }

    /**
     * Create a new invitation.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function invite(string $email, array $metadata = []): Invitation
    {
        $invitation = $this->invitations()->create([
            'email' => $email,
            'code' => Invitation::generateCode(),
            'token' => Invitation::generateToken(),
            'status' => Invitation::STATUS_PENDING,
            'expires_at' => Invitation::calculateExpiresAt(),
            'metadata' => $metadata,
        ]);

        event(new InvitationCreated($invitation, $this));

        // Send notification
        $this->sendInvitationNotification($invitation);

        return $invitation;
    }

    /**
     * Get all pending invitations created by this user.
     */
    public function pendingInvitations(): Collection
    {
        return $this->invitations()->pending()->get();
    }

    /**
     * Send the invitation notification.
     */
    protected function sendInvitationNotification(Invitation $invitation): void
    {
        $notification = new InvitationNotification($invitation);

        // Use Laravel's anonymous notifiable
        $notifiable = new AnonymousNotifiable();
        $notifiable->route('mail', $invitation->email);

        $notifiable->notify($notification);
    }
}
