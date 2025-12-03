<?php

namespace Qisthidev\AuthDevice\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Device extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'name',
        'device_token',
        'device_fingerprint',
        'platform',
        'last_used_at',
        'last_ip_address',
        'is_active',
        'verified_at',
        'expires_at',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'metadata' => 'array',
        'last_used_at' => 'datetime',
        'verified_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'device_token',
    ];

    /**
     * Get the table associated with the model.
     */
    public function getTable(): string
    {
        return config('auth-device.tables.devices', 'auth_devices');
    }

    /**
     * Get the user that owns the device.
     */
    public function user(): BelongsTo
    {
        $userModel = config('auth-device.models.user', 'App\\Models\\User');

        return $this->belongsTo($userModel);
    }

    /**
     * Scope a query to only include active devices.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include expired devices.
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('expires_at', '<', now());
    }

    /**
     * Scope a query to filter by platform.
     */
    public function scopePlatform(Builder $query, string $platform): Builder
    {
        return $query->where('platform', $platform);
    }

    /**
     * Check if the device is active.
     */
    public function isActive(): bool
    {
        return $this->is_active === true;
    }

    /**
     * Check if the device has expired.
     */
    public function isExpired(): bool
    {
        if ($this->expires_at === null) {
            return false;
        }

        return $this->expires_at->isPast();
    }

    /**
     * Mark the device as used (update last_used_at and last_ip_address).
     */
    public function markAsUsed(?string $ipAddress = null): self
    {
        $this->update([
            'last_used_at' => now(),
            'last_ip_address' => $ipAddress ?? request()->ip(),
        ]);

        return $this;
    }

    /**
     * Revoke the device (deactivate it).
     */
    public function revoke(): self
    {
        $this->update([
            'is_active' => false,
        ]);

        return $this;
    }

    /**
     * Generate a secure device token.
     */
    public static function generateToken(): string
    {
        $length = config('auth-device.device_token_length', 64);

        return Str::random($length);
    }

    /**
     * Calculate the expiration date for a new device.
     */
    public static function calculateExpiresAt(): ?\DateTime
    {
        $expiryDays = config('auth-device.device_token_expiry_days');

        if ($expiryDays === null) {
            return null;
        }

        return now()->addDays($expiryDays)->toDateTime();
    }
}
