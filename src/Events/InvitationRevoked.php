<?php

namespace Qisthidev\AuthDevice\Events;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Qisthidev\AuthDevice\Models\Invitation;

class InvitationRevoked
{
    use Dispatchable;
    use SerializesModels;

    public Invitation $invitation;

    public Authenticatable $revoker;

    /**
     * Create a new event instance.
     */
    public function __construct(Invitation $invitation, Authenticatable $revoker)
    {
        $this->invitation = $invitation;
        $this->revoker = $revoker;
    }
}
