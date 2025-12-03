# Laravel Auth Device

[![Latest Version on Packagist](https://img.shields.io/packagist/v/qisthidev/laravel-auth-device.svg?style=flat-square)](https://packagist.org/packages/qisthidev/laravel-auth-device)
[![GitHub Tests Action Status](https://img.shields.io/github/workflow/status/qisthidev/laravel-auth-device/run-tests?label=tests)](https://github.com/qisthidev/laravel-auth-device/actions?query=workflow%3Arun-tests+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/qisthidev/laravel-auth-device.svg?style=flat-square)](https://packagist.org/packages/qisthidev/laravel-auth-device)

A Laravel package for device-based authentication. Users authenticate using registered devices (without passwords) and access is granted through admin invitations.

## Features

- **Device-based Authentication**: Authenticate users via registered devices using unique device tokens
- **Invitation System**: Admin users can invite new users via email
- **Device Management**: Users can manage their registered devices
- **Custom Guard**: Dedicated authentication guard for device-based auth
- **Flexible Configuration**: Customizable token lengths, expiry times, and more
- **Event System**: Events for all major actions (device registered, authenticated, revoked, etc.)

## Requirements

- PHP 8.2+
- Laravel 12.x

## Installation

Install the package via Composer:

```bash
composer require qisthidev/laravel-auth-device
```

Publish and run the migrations:

```bash
php artisan vendor:publish --provider="Qisthidev\AuthDevice\AuthDeviceServiceProvider" --tag="auth-device-migrations"
php artisan migrate
```

Publish the config file:

```bash
php artisan vendor:publish --provider="Qisthidev\AuthDevice\AuthDeviceServiceProvider" --tag="auth-device-config"
```

## Configuration

The config file `config/auth-device.php` contains the following options:

```php
return [
    // Device token settings
    'device_token_length' => 64,
    'device_token_expiry_days' => 365, // null for no expiry
    
    // Invitation settings
    'invitation_code_length' => 8,
    'invitation_expiry_hours' => 48,
    
    // Security settings
    'max_devices_per_user' => 5, // null for unlimited
    'require_device_verification' => false,
    
    // Routes
    'route_prefix' => 'api/auth',
    'route_middleware' => ['api'],
    
    // Models (allow customization)
    'models' => [
        'user' => 'App\\Models\\User',
        'device' => Qisthidev\AuthDevice\Models\Device::class,
        'invitation' => Qisthidev\AuthDevice\Models\Invitation::class,
    ],
    
    // Table names
    'tables' => [
        'devices' => 'auth_devices',
        'invitations' => 'auth_invitations',
    ],
];
```

## Auth Guard Configuration

Add the device guard to your `config/auth.php`:

```php
'guards' => [
    // ... other guards
    'device' => [
        'driver' => 'device',
        'provider' => 'device',
    ],
],

'providers' => [
    // ... other providers
    'device' => [
        'driver' => 'device',
    ],
],
```

## Setting Up Your User Model

Add the required traits to your User model:

```php
<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Qisthidev\AuthDevice\Traits\HasDevices;
use Qisthidev\AuthDevice\Traits\CanInvite;

class User extends Authenticatable
{
    use HasDevices, CanInvite;
    
    // ... rest of your model
}
```

## Usage

### Admin Inviting a User

```php
use App\Models\User;

$admin = User::find(1);

// Create an invitation
$invitation = $admin->invite('newuser@example.com', [
    'role' => 'member',
    'department' => 'Engineering',
]);

// Get pending invitations
$pendingInvitations = $admin->pendingInvitations();
```

### User Accepting Invitation and Registering Device

Send a POST request to `/api/auth/invitation/{code}/accept`:

```json
{
    "name": "John Doe",
    "device_name": "iPhone 15 Pro",
    "device_fingerprint": "unique-device-id",
    "platform": "ios"
}
```

Response:

```json
{
    "message": "Invitation accepted successfully.",
    "user": {
        "id": 2,
        "name": "John Doe",
        "email": "newuser@example.com"
    },
    "device": {
        "id": 1,
        "name": "iPhone 15 Pro",
        "platform": "ios"
    },
    "token": "your-device-token-here"
}
```

### Authenticating with Device Token

Send a POST request to `/api/auth/authenticate`:

```json
{
    "device_token": "your-device-token-here"
}
```

Or include the token in the Authorization header for protected routes:

```
Authorization: Bearer your-device-token-here
```

Or use the X-Device-Token header:

```
X-Device-Token: your-device-token-here
```

### Managing Devices

```php
use App\Models\User;

$user = User::find(1);

// Get all devices
$devices = $user->devices;

// Get active devices only
$activeDevices = $user->activeDevices();

// Register a new device
$device = $user->registerDevice([
    'name' => 'Chrome on Windows',
    'platform' => 'web',
    'device_fingerprint' => 'unique-browser-fingerprint',
    'metadata' => ['user_agent' => 'Mozilla/5.0...'],
]);

// Check if user has a specific device
$hasDevice = $user->hasDevice($deviceToken);

// Revoke a specific device
$user->revokeDevice($deviceId);

// Revoke all devices
$user->revokeAllDevices();
```

## API Endpoints

### Public Routes

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/auth/invitation/{code}` | Get invitation details by code |
| POST | `/api/auth/invitation/{code}/accept` | Accept invitation and register device |
| POST | `/api/auth/authenticate` | Authenticate using device token |

### Protected Routes (requires device authentication)

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/auth/logout` | Logout (revoke current device) |
| GET | `/api/auth/devices` | List user's devices |
| DELETE | `/api/auth/devices/{id}` | Revoke specific device |

### Admin Routes (requires device authentication + CanInvite trait)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/auth/invitations` | List all invitations |
| POST | `/api/auth/invitations` | Create new invitation |
| DELETE | `/api/auth/invitations/{id}` | Revoke invitation |

## Events

The package dispatches the following events:

| Event | Description |
|-------|-------------|
| `DeviceRegistered` | When a new device is registered |
| `DeviceAuthenticated` | When a user authenticates via device |
| `DeviceRevoked` | When a device is revoked |
| `InvitationCreated` | When an admin creates an invitation |
| `InvitationAccepted` | When a user accepts an invitation |
| `InvitationRevoked` | When an invitation is revoked |

### Listening to Events

```php
// In your EventServiceProvider
protected $listen = [
    \Qisthidev\AuthDevice\Events\DeviceRegistered::class => [
        \App\Listeners\SendDeviceRegisteredNotification::class,
    ],
    \Qisthidev\AuthDevice\Events\DeviceAuthenticated::class => [
        \App\Listeners\LogDeviceAuthentication::class,
    ],
];
```

## Middleware

The package provides the following middleware:

- `device.valid` - Ensures the device token is valid, active, and not expired
- `can-invite` - Ensures the authenticated user can create invitations

## Security Considerations

1. **Token Storage**: Device tokens should be stored securely on the client side
2. **Token Rotation**: Consider implementing token rotation for enhanced security
3. **Rate Limiting**: Apply rate limiting to authentication endpoints
4. **HTTPS**: Always use HTTPS in production
5. **Token Expiry**: Configure appropriate token expiry times
6. **Device Limits**: Set reasonable limits on devices per user

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](.github/CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Qisthidev](https://github.com/qisthidev)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
