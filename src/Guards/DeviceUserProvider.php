<?php

namespace Qisthidev\AuthDevice\Guards;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;
use Qisthidev\AuthDevice\Models\Device;

class DeviceUserProvider implements UserProvider
{
    protected string $userModel;

    protected string $deviceModel;

    public function __construct()
    {
        $this->userModel = config('auth-device.models.user', 'App\\Models\\User');
        $this->deviceModel = config('auth-device.models.device', Device::class);
    }

    /**
     * Retrieve a user by their unique identifier.
     *
     * @param  mixed  $identifier
     */
    public function retrieveById($identifier): ?Authenticatable
    {
        return $this->userModel::find($identifier);
    }

    /**
     * Retrieve a user by their unique identifier and "remember me" token.
     *
     * @param  mixed  $identifier
     * @param  string  $token
     */
    public function retrieveByToken($identifier, $token): ?Authenticatable
    {
        // Not used for device-based authentication
        return null;
    }

    /**
     * Update the "remember me" token for the given user in storage.
     *
     * @param  string  $token
     */
    public function updateRememberToken(Authenticatable $user, $token): void
    {
        // Not used for device-based authentication
    }

    /**
     * Retrieve a user by the given credentials.
     *
     * @param  array<string, mixed>  $credentials
     */
    public function retrieveByCredentials(array $credentials): ?Authenticatable
    {
        $deviceToken = $credentials['device_token'] ?? null;

        if ($deviceToken === null) {
            return null;
        }

        $result = $this->retrieveByDeviceToken($deviceToken);

        return $result ? $result[0] : null;
    }

    /**
     * Validate a user against the given credentials.
     *
     * @param  array<string, mixed>  $credentials
     */
    public function validateCredentials(Authenticatable $user, array $credentials): bool
    {
        $deviceToken = $credentials['device_token'] ?? null;

        if ($deviceToken === null) {
            return false;
        }

        return $this->deviceModel::where('user_id', $user->getAuthIdentifier())
            ->where('device_token', $deviceToken)
            ->active()
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->exists();
    }

    /**
     * Retrieve a user and device by the device token.
     *
     * @return array{0: Authenticatable, 1: Device}|null
     */
    public function retrieveByDeviceToken(string $token): ?array
    {
        $device = $this->deviceModel::where('device_token', $token)
            ->active()
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->first();

        if ($device === null) {
            return null;
        }

        $user = $this->userModel::find($device->user_id);

        if ($user === null) {
            return null;
        }

        return [$user, $device];
    }

    /**
     * Rehash the user's password if required and supported.
     *
     * @param  bool  $force
     */
    public function rehashPasswordIfRequired(Authenticatable $user, array $credentials, $force = false): void
    {
        // Not used for device-based authentication
    }
}
