<?php

namespace Qisthidev\AuthDevice\Events;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Qisthidev\AuthDevice\Models\Device;

class DeviceRevoked
{
    use Dispatchable, SerializesModels;

    public Device $device;

    public Authenticatable $user;

    /**
     * Create a new event instance.
     */
    public function __construct(Device $device, Authenticatable $user)
    {
        $this->device = $device;
        $this->user = $user;
    }
}
