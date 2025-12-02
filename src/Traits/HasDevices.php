<?php

namespace Qisthidev\AuthDevice\Traits;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Qisthidev\AuthDevice\Events\DeviceRegistered;
use Qisthidev\AuthDevice\Events\DeviceRevoked;
use Qisthidev\AuthDevice\Models\Device;

trait HasDevices
{
    /**
     * Get all devices for the user.
     */
    public function devices(): HasMany
    {
        $deviceModel = config('auth-device.models.device', Device::class);

        return $this->hasMany($deviceModel);
    }

    /**
     * Get all active devices for the user.
     */
    public function activeDevices(): Collection
    {
        return $this->devices()->active()->get();
    }

    /**
     * Register a new device for the user.
     *
     * @param  array<string, mixed>  $deviceData
     *
     * @throws \Exception
     */
    public function registerDevice(array $deviceData): Device
    {
        $maxDevices = config('auth-device.max_devices_per_user');

        if ($maxDevices !== null && $this->devices()->active()->count() >= $maxDevices) {
            throw new \Exception('Maximum number of devices reached.');
        }

        $device = $this->devices()->create([
            'name' => $deviceData['name'] ?? 'Unknown Device',
            'device_token' => $deviceData['device_token'] ?? Device::generateToken(),
            'device_fingerprint' => $deviceData['device_fingerprint'] ?? null,
            'platform' => $deviceData['platform'] ?? 'web',
            'last_used_at' => now(),
            'last_ip_address' => $deviceData['ip_address'] ?? request()->ip(),
            'is_active' => true,
            'verified_at' => config('auth-device.require_device_verification') ? null : now(),
            'expires_at' => Device::calculateExpiresAt(),
            'metadata' => $deviceData['metadata'] ?? null,
        ]);

        event(new DeviceRegistered($device, $this));

        return $device;
    }

    /**
     * Check if the user has a device with the given token.
     */
    public function hasDevice(string $deviceToken): bool
    {
        return $this->devices()
            ->where('device_token', $deviceToken)
            ->active()
            ->exists();
    }

    /**
     * Revoke a specific device.
     */
    public function revokeDevice(int $deviceId): bool
    {
        $device = $this->devices()->find($deviceId);

        if (! $device) {
            return false;
        }

        $device->revoke();

        event(new DeviceRevoked($device, $this));

        return true;
    }

    /**
     * Revoke all devices for the user.
     */
    public function revokeAllDevices(): int
    {
        $devices = $this->devices()->active()->get();

        foreach ($devices as $device) {
            $device->revoke();
            event(new DeviceRevoked($device, $this));
        }

        return $devices->count();
    }
}
