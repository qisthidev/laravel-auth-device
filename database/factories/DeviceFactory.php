<?php

namespace Qisthidev\AuthDevice\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Qisthidev\AuthDevice\Models\Device;

class DeviceFactory extends Factory
{
    protected $model = Device::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->randomElement(['iPhone 15', 'Samsung Galaxy', 'Chrome on Windows', 'Safari on Mac']),
            'device_token' => Device::generateToken(),
            'device_fingerprint' => $this->faker->uuid(),
            'platform' => $this->faker->randomElement(['ios', 'android', 'web', 'desktop']),
            'last_used_at' => now(),
            'last_ip_address' => $this->faker->ipv4(),
            'is_active' => true,
            'verified_at' => now(),
            'expires_at' => Device::calculateExpiresAt(),
            'metadata' => [
                'user_agent' => $this->faker->userAgent(),
            ],
        ];
    }

    /**
     * Indicate that the device is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the device is expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subDay(),
        ]);
    }

    /**
     * Indicate that the device is unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'verified_at' => null,
        ]);
    }
}
