<?php

namespace Qisthidev\AuthDevice\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Invitation extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_REVOKED = 'revoked';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'invited_by',
        'email',
        'code',
        'token',
        'status',
        'expires_at',
        'accepted_at',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'metadata' => 'array',
        'expires_at' => 'datetime',
        'accepted_at' => 'datetime',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'token',
    ];

    /**
     * Get the table associated with the model.
     */
    public function getTable(): string
    {
        return config('auth-device.tables.invitations', 'auth_invitations');
    }

    /**
     * Get the user who created the invitation.
     */
    public function inviter(): BelongsTo
    {
        $userModel = config('auth-device.models.user', 'App\\Models\\User');

        return $this->belongsTo($userModel, 'invited_by');
    }

    /**
     * Scope a query to only include pending invitations.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope a query to only include expired invitations.
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('expires_at', '<', now());
    }

    /**
     * Scope a query to filter by email.
     */
    public function scopeForEmail(Builder $query, string $email): Builder
    {
        return $query->where('email', $email);
    }

    /**
     * Check if the invitation is pending.
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if the invitation has expired.
     */
    public function isExpired(): bool
    {
        if ($this->expires_at === null) {
            return false;
        }

        return $this->expires_at->isPast();
    }

    /**
     * Accept the invitation.
     */
    public function accept(): self
    {
        $this->update([
            'status' => self::STATUS_ACCEPTED,
            'accepted_at' => now(),
        ]);

        return $this;
    }

    /**
     * Revoke the invitation.
     */
    public function revoke(): self
    {
        $this->update([
            'status' => self::STATUS_REVOKED,
        ]);

        return $this;
    }

    /**
     * Generate a unique invitation code.
     */
    public static function generateCode(): string
    {
        $length = config('auth-device.invitation_code_length', 8);

        return strtoupper(Str::random($length));
    }

    /**
     * Generate a secure invitation token.
     */
    public static function generateToken(): string
    {
        return Str::random(64);
    }

    /**
     * Calculate the expiration date for a new invitation.
     */
    public static function calculateExpiresAt(): \DateTime
    {
        $expiryHours = config('auth-device.invitation_expiry_hours', 48);

        return now()->addHours($expiryHours)->toDateTime();
    }
}
