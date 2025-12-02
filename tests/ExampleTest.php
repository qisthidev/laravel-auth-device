<?php

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Qisthidev\AuthDevice\Events\DeviceAuthenticated;
use Qisthidev\AuthDevice\Events\DeviceRegistered;
use Qisthidev\AuthDevice\Events\DeviceRevoked;
use Qisthidev\AuthDevice\Events\InvitationAccepted;
use Qisthidev\AuthDevice\Events\InvitationCreated;
use Qisthidev\AuthDevice\Events\InvitationRevoked;
use Qisthidev\AuthDevice\Models\Device;
use Qisthidev\AuthDevice\Models\Invitation;
use Qisthidev\AuthDevice\Tests\Models\User;

beforeEach(function () {
    config()->set('auth-device.models.user', User::class);
});

it('can generate a device token', function () {
    $token = Device::generateToken();

    expect($token)->toBeString()
        ->and(strlen($token))->toBe(64);
});

it('can generate an invitation code', function () {
    $code = Invitation::generateCode();

    expect($code)->toBeString()
        ->and(strlen($code))->toBe(8);
});

it('can register a device for a user', function () {
    Event::fake([DeviceRegistered::class]);

    $user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => Hash::make('password'),
    ]);

    $device = $user->registerDevice([
        'name' => 'Test Device',
        'platform' => 'web',
    ]);

    expect($device)->toBeInstanceOf(Device::class)
        ->and($device->name)->toBe('Test Device')
        ->and($device->platform)->toBe('web')
        ->and($device->is_active)->toBeTrue()
        ->and($user->devices()->count())->toBe(1);

    Event::assertDispatched(DeviceRegistered::class);
});

it('can check if device is active', function () {
    $user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => Hash::make('password'),
    ]);

    $device = $user->registerDevice([
        'name' => 'Test Device',
    ]);

    expect($device->isActive())->toBeTrue();

    $device->revoke();

    expect($device->isActive())->toBeFalse();
});

it('can check if device is expired', function () {
    $user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => Hash::make('password'),
    ]);

    $device = $user->registerDevice([
        'name' => 'Test Device',
    ]);

    expect($device->isExpired())->toBeFalse();

    $device->update(['expires_at' => now()->subDay()]);

    expect($device->isExpired())->toBeTrue();
});

it('can revoke a device', function () {
    Event::fake([DeviceRegistered::class, DeviceRevoked::class]);

    $user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => Hash::make('password'),
    ]);

    $device = $user->registerDevice([
        'name' => 'Test Device',
    ]);

    expect($device->is_active)->toBeTrue();

    $user->revokeDevice($device->id);

    $device->refresh();
    expect($device->is_active)->toBeFalse();

    Event::assertDispatched(DeviceRevoked::class);
});

it('can revoke all devices for a user', function () {
    Event::fake([DeviceRegistered::class, DeviceRevoked::class]);

    config()->set('auth-device.max_devices_per_user', null);

    $user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => Hash::make('password'),
    ]);

    $user->registerDevice(['name' => 'Device 1']);
    $user->registerDevice(['name' => 'Device 2']);
    $user->registerDevice(['name' => 'Device 3']);

    expect($user->activeDevices()->count())->toBe(3);

    $count = $user->revokeAllDevices();

    expect($count)->toBe(3)
        ->and($user->activeDevices()->count())->toBe(0);

    Event::assertDispatchedTimes(DeviceRevoked::class, 3);
});

it('can create an invitation', function () {
    Event::fake([InvitationCreated::class]);

    $admin = User::create([
        'name' => 'Admin User',
        'email' => 'admin@example.com',
        'password' => Hash::make('password'),
    ]);

    $invitation = $admin->invite('newuser@example.com');

    expect($invitation)->toBeInstanceOf(Invitation::class)
        ->and($invitation->email)->toBe('newuser@example.com')
        ->and($invitation->status)->toBe(Invitation::STATUS_PENDING)
        ->and($invitation->isPending())->toBeTrue();

    Event::assertDispatched(InvitationCreated::class);
});

it('can accept an invitation', function () {
    Event::fake([InvitationCreated::class]);

    $admin = User::create([
        'name' => 'Admin User',
        'email' => 'admin@example.com',
        'password' => Hash::make('password'),
    ]);

    $invitation = $admin->invite('newuser@example.com');
    $invitation->accept();

    expect($invitation->status)->toBe(Invitation::STATUS_ACCEPTED)
        ->and($invitation->isPending())->toBeFalse()
        ->and($invitation->accepted_at)->not()->toBeNull();
});

it('can revoke an invitation', function () {
    Event::fake([InvitationCreated::class]);

    $admin = User::create([
        'name' => 'Admin User',
        'email' => 'admin@example.com',
        'password' => Hash::make('password'),
    ]);

    $invitation = $admin->invite('newuser@example.com');
    $invitation->revoke();

    expect($invitation->status)->toBe(Invitation::STATUS_REVOKED)
        ->and($invitation->isPending())->toBeFalse();
});

it('can check if invitation is expired', function () {
    Event::fake([InvitationCreated::class]);

    $admin = User::create([
        'name' => 'Admin User',
        'email' => 'admin@example.com',
        'password' => Hash::make('password'),
    ]);

    $invitation = $admin->invite('newuser@example.com');

    expect($invitation->isExpired())->toBeFalse();

    $invitation->update(['expires_at' => now()->subDay()]);

    expect($invitation->isExpired())->toBeTrue();
});

it('can get pending invitations', function () {
    Event::fake([InvitationCreated::class]);

    $admin = User::create([
        'name' => 'Admin User',
        'email' => 'admin@example.com',
        'password' => Hash::make('password'),
    ]);

    $admin->invite('user1@example.com');
    $admin->invite('user2@example.com');
    $invitation3 = $admin->invite('user3@example.com');
    $invitation3->accept();

    expect($admin->pendingInvitations()->count())->toBe(2);
});

it('enforces max devices per user', function () {
    Event::fake([DeviceRegistered::class]);

    config()->set('auth-device.max_devices_per_user', 2);

    $user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => Hash::make('password'),
    ]);

    $user->registerDevice(['name' => 'Device 1']);
    $user->registerDevice(['name' => 'Device 2']);

    expect(fn () => $user->registerDevice(['name' => 'Device 3']))
        ->toThrow(\Exception::class, 'Maximum number of devices reached.');
});

it('can check if user has a device token', function () {
    Event::fake([DeviceRegistered::class]);

    $user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => Hash::make('password'),
    ]);

    $device = $user->registerDevice(['name' => 'Test Device']);

    expect($user->hasDevice($device->device_token))->toBeTrue()
        ->and($user->hasDevice('invalid-token'))->toBeFalse();
});

it('can mark device as used', function () {
    Event::fake([DeviceRegistered::class]);

    $user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => Hash::make('password'),
    ]);

    $device = $user->registerDevice(['name' => 'Test Device']);
    $originalLastUsed = $device->last_used_at;

    sleep(1);
    $device->markAsUsed('192.168.1.100');

    $device->refresh();
    expect($device->last_ip_address)->toBe('192.168.1.100')
        ->and($device->last_used_at->gt($originalLastUsed))->toBeTrue();
});

it('can authenticate using device token', function () {
    Event::fake([DeviceRegistered::class, DeviceAuthenticated::class]);

    $user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => Hash::make('password'),
    ]);

    $device = $user->registerDevice(['name' => 'Test Device']);

    $response = $this->postJson('/api/auth/authenticate', [
        'device_token' => $device->device_token,
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'message',
            'user',
            'device',
            'token',
        ]);
});

it('rejects invalid device token', function () {
    $response = $this->postJson('/api/auth/authenticate', [
        'device_token' => 'invalid-token',
    ]);

    $response->assertStatus(401)
        ->assertJson([
            'message' => 'Invalid device token.',
        ]);
});

it('can get invitation by code', function () {
    Event::fake([InvitationCreated::class]);

    $admin = User::create([
        'name' => 'Admin User',
        'email' => 'admin@example.com',
        'password' => Hash::make('password'),
    ]);

    $invitation = $admin->invite('newuser@example.com');

    $response = $this->getJson("/api/auth/invitation/{$invitation->code}");

    $response->assertStatus(200)
        ->assertJsonStructure([
            'invitation' => [
                'code',
                'email',
                'expires_at',
            ],
        ]);
});

it('returns 404 for invalid invitation code', function () {
    $response = $this->getJson('/api/auth/invitation/INVALID');

    $response->assertStatus(404)
        ->assertJson([
            'message' => 'Invitation not found.',
        ]);
});

it('can accept invitation and register device', function () {
    Event::fake([InvitationCreated::class, InvitationAccepted::class]);

    $admin = User::create([
        'name' => 'Admin User',
        'email' => 'admin@example.com',
        'password' => Hash::make('password'),
    ]);

    $invitation = $admin->invite('newuser@example.com');

    $response = $this->postJson("/api/auth/invitation/{$invitation->code}/accept", [
        'name' => 'New User',
        'device_name' => 'iPhone 15',
        'platform' => 'ios',
    ]);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'message',
            'user',
            'device',
            'token',
        ]);

    Event::assertDispatched(InvitationAccepted::class);

    $invitation->refresh();
    expect($invitation->status)->toBe(Invitation::STATUS_ACCEPTED);
});

it('can list user devices when authenticated', function () {
    Event::fake([DeviceRegistered::class, DeviceAuthenticated::class]);

    $user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => Hash::make('password'),
    ]);

    $device = $user->registerDevice(['name' => 'Test Device']);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$device->device_token}",
    ])->getJson('/api/auth/devices');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'devices' => [
                '*' => [
                    'id',
                    'name',
                    'platform',
                    'is_active',
                ],
            ],
        ]);
});

it('can revoke device when authenticated', function () {
    Event::fake([DeviceRegistered::class, DeviceAuthenticated::class, DeviceRevoked::class]);

    config()->set('auth-device.max_devices_per_user', null);

    $user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => Hash::make('password'),
    ]);

    $device1 = $user->registerDevice(['name' => 'Device 1']);
    $device2 = $user->registerDevice(['name' => 'Device 2']);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$device1->device_token}",
    ])->deleteJson("/api/auth/devices/{$device2->id}");

    $response->assertStatus(200)
        ->assertJson([
            'message' => 'Device revoked successfully.',
        ]);

    $device2->refresh();
    expect($device2->is_active)->toBeFalse();
});

it('can logout current device', function () {
    Event::fake([DeviceRegistered::class, DeviceAuthenticated::class, DeviceRevoked::class]);

    $user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => Hash::make('password'),
    ]);

    $device = $user->registerDevice(['name' => 'Test Device']);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$device->device_token}",
    ])->postJson('/api/auth/logout');

    $response->assertStatus(200)
        ->assertJson([
            'message' => 'Logged out successfully.',
        ]);

    $device->refresh();
    expect($device->is_active)->toBeFalse();
});

it('can list invitations when admin authenticated', function () {
    Event::fake([DeviceRegistered::class, DeviceAuthenticated::class, InvitationCreated::class]);

    $admin = User::create([
        'name' => 'Admin User',
        'email' => 'admin@example.com',
        'password' => Hash::make('password'),
    ]);

    $device = $admin->registerDevice(['name' => 'Admin Device']);
    $admin->invite('user1@example.com');
    $admin->invite('user2@example.com');

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$device->device_token}",
    ])->getJson('/api/auth/invitations');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'invitations' => [
                '*' => [
                    'id',
                    'email',
                    'code',
                    'status',
                ],
            ],
        ]);
});

it('can create invitation when admin authenticated', function () {
    Event::fake([DeviceRegistered::class, DeviceAuthenticated::class, InvitationCreated::class]);

    $admin = User::create([
        'name' => 'Admin User',
        'email' => 'admin@example.com',
        'password' => Hash::make('password'),
    ]);

    $device = $admin->registerDevice(['name' => 'Admin Device']);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$device->device_token}",
    ])->postJson('/api/auth/invitations', [
        'email' => 'newuser@example.com',
    ]);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'message',
            'invitation' => [
                'id',
                'email',
                'code',
                'expires_at',
            ],
        ]);
});

it('can revoke invitation when admin authenticated', function () {
    Event::fake([DeviceRegistered::class, DeviceAuthenticated::class, InvitationCreated::class, InvitationRevoked::class]);

    $admin = User::create([
        'name' => 'Admin User',
        'email' => 'admin@example.com',
        'password' => Hash::make('password'),
    ]);

    $device = $admin->registerDevice(['name' => 'Admin Device']);
    $invitation = $admin->invite('user@example.com');

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$device->device_token}",
    ])->deleteJson("/api/auth/invitations/{$invitation->id}");

    $response->assertStatus(200)
        ->assertJson([
            'message' => 'Invitation revoked successfully.',
        ]);

    $invitation->refresh();
    expect($invitation->status)->toBe(Invitation::STATUS_REVOKED);
});
