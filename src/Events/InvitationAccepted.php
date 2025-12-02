<?php

namespace Qisthidev\AuthDevice\Events;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Qisthidev\AuthDevice\Models\Invitation;

class InvitationAccepted
{
    use Dispatchable, SerializesModels;

    public Invitation $invitation;

    public Authenticatable $user;

    /**
     * Create a new event instance.
     */
    public function __construct(Invitation $invitation, Authenticatable $user)
    {
        $this->invitation = $invitation;
        $this->user = $user;
    }
}
