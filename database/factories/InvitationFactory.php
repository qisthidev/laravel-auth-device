<?php

namespace Qisthidev\AuthDevice\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Qisthidev\AuthDevice\Models\Invitation;

class InvitationFactory extends Factory
{
    protected $model = Invitation::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => $this->faker->unique()->safeEmail(),
            'code' => Invitation::generateCode(),
            'token' => Invitation::generateToken(),
            'status' => Invitation::STATUS_PENDING,
            'expires_at' => Invitation::calculateExpiresAt(),
            'metadata' => null,
        ];
    }

    /**
     * Indicate that the invitation is accepted.
     */
    public function accepted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Invitation::STATUS_ACCEPTED,
            'accepted_at' => now(),
        ]);
    }

    /**
     * Indicate that the invitation is expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Invitation::STATUS_EXPIRED,
            'expires_at' => now()->subDay(),
        ]);
    }

    /**
     * Indicate that the invitation is revoked.
     */
    public function revoked(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Invitation::STATUS_REVOKED,
        ]);
    }
}
