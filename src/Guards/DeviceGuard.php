<?php

namespace Qisthidev\AuthDevice\Guards;

use Illuminate\Auth\GuardHelpers;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Http\Request;
use Qisthidev\AuthDevice\Events\DeviceAuthenticated;
use Qisthidev\AuthDevice\Models\Device;

class DeviceGuard implements Guard
{
    use GuardHelpers;

    protected Request $request;

    protected ?Device $currentDevice = null;

    public function __construct(DeviceUserProvider $provider, Request $request)
    {
        $this->provider = $provider;
        $this->request = $request;
    }

    /**
     * Get the currently authenticated user.
     */
    public function user(): ?Authenticatable
    {
        if ($this->user !== null) {
            return $this->user;
        }

        $token = $this->getTokenFromRequest();

        if ($token === null) {
            return null;
        }

        $result = $this->getDeviceUserProvider()->retrieveByDeviceToken($token);

        if ($result === null) {
            return null;
        }

        [$user, $device] = $result;

        $this->user = $user;
        $this->currentDevice = $device;

        // Update last used timestamp
        $device->markAsUsed($this->request->ip());

        event(new DeviceAuthenticated($device, $user));

        return $this->user;
    }

    /**
     * Validate a user's credentials.
     *
     * @param  array<string, mixed>  $credentials
     */
    public function validate(array $credentials = []): bool
    {
        $token = $credentials['device_token'] ?? null;

        if ($token === null) {
            return false;
        }

        $result = $this->getDeviceUserProvider()->retrieveByDeviceToken($token);

        return $result !== null;
    }

    /**
     * Get the device token from the request.
     */
    protected function getTokenFromRequest(): ?string
    {
        // First, try to get from Authorization header (Bearer token)
        $token = $this->request->bearerToken();

        if ($token !== null) {
            return $token;
        }

        // Then, try to get from X-Device-Token header
        $token = $this->request->header('X-Device-Token');

        if ($token !== null) {
            return $token;
        }

        // Finally, try to get from request input
        return $this->request->input('device_token');
    }

    /**
     * Get the current device.
     */
    public function device(): ?Device
    {
        // Ensure user() is called to populate currentDevice
        $this->user();

        return $this->currentDevice;
    }

    /**
     * Set the current request instance.
     */
    public function setRequest(Request $request): self
    {
        $this->request = $request;

        return $this;
    }

    /**
     * Get the DeviceUserProvider instance.
     */
    protected function getDeviceUserProvider(): DeviceUserProvider
    {
        return $this->provider;
    }
}
