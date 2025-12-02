<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Device Token Settings
    |--------------------------------------------------------------------------
    |
    | Configure the length and expiry of device tokens.
    |
    */
    'device_token_length' => 64,
    'device_token_expiry_days' => 365, // null for no expiry

    /*
    |--------------------------------------------------------------------------
    | Invitation Settings
    |--------------------------------------------------------------------------
    |
    | Configure the invitation code length and expiry time.
    |
    */
    'invitation_code_length' => 8,
    'invitation_expiry_hours' => 48,

    /*
    |--------------------------------------------------------------------------
    | Security Settings
    |--------------------------------------------------------------------------
    |
    | Configure security-related settings for device authentication.
    |
    */
    'max_devices_per_user' => 5, // null for unlimited
    'require_device_verification' => false,

    /*
    |--------------------------------------------------------------------------
    | Route Settings
    |--------------------------------------------------------------------------
    |
    | Configure the route prefix and middleware for the package routes.
    |
    */
    'route_prefix' => 'api/auth',
    'route_middleware' => ['api'],

    /*
    |--------------------------------------------------------------------------
    | Models
    |--------------------------------------------------------------------------
    |
    | Specify custom models to use with the package.
    |
    */
    'models' => [
        'user' => 'App\\Models\\User',
        'device' => Qisthidev\AuthDevice\Models\Device::class,
        'invitation' => Qisthidev\AuthDevice\Models\Invitation::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Table Names
    |--------------------------------------------------------------------------
    |
    | Specify custom table names for the package tables.
    |
    */
    'tables' => [
        'devices' => 'auth_devices',
        'invitations' => 'auth_invitations',
    ],
];
