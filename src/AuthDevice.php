<?php

namespace Qisthidev\AuthDevice;

class AuthDevice
{
    /**
     * Get the configured device model class.
     */
    public function getDeviceModel(): string
    {
        return config('auth-device.models.device');
    }

    /**
     * Get the configured invitation model class.
     */
    public function getInvitationModel(): string
    {
        return config('auth-device.models.invitation');
    }

    /**
     * Get the configured user model class.
     */
    public function getUserModel(): string
    {
        return config('auth-device.models.user');
    }

    /**
     * Get the maximum number of devices per user.
     */
    public function getMaxDevicesPerUser(): ?int
    {
        return config('auth-device.max_devices_per_user');
    }

    /**
     * Check if device verification is required.
     */
    public function requiresDeviceVerification(): bool
    {
        return config('auth-device.require_device_verification', false);
    }
}
