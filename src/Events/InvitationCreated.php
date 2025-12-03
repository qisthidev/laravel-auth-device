<?php

namespace Qisthidev\AuthDevice\Events;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Qisthidev\AuthDevice\Models\Invitation;

class InvitationCreated
{
    use Dispatchable;
    use SerializesModels;

    public Invitation $invitation;

    public Authenticatable $inviter;

    /**
     * Create a new event instance.
     */
    public function __construct(Invitation $invitation, Authenticatable $inviter)
    {
        $this->invitation = $invitation;
        $this->inviter = $inviter;
    }
}
