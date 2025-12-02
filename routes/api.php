<?php

use Illuminate\Support\Facades\Route;
use Qisthidev\AuthDevice\Http\Controllers\DeviceAuthController;
use Qisthidev\AuthDevice\Http\Controllers\InvitationController;

$prefix = config('auth-device.route_prefix', 'api/auth');
$middleware = config('auth-device.route_middleware', ['api']);

Route::prefix($prefix)->middleware($middleware)->group(function () {
    // Public routes
    Route::post('/invitation/{code}/accept', [InvitationController::class, 'accept']);
    Route::get('/invitation/{code}', [InvitationController::class, 'show']);
    Route::post('/authenticate', [DeviceAuthController::class, 'authenticate']);

    // Protected routes (device authenticated)
    Route::middleware('auth:device')->group(function () {
        Route::post('/logout', [DeviceAuthController::class, 'logout']);
        Route::get('/devices', [DeviceAuthController::class, 'devices']);
        Route::delete('/devices/{id}', [DeviceAuthController::class, 'revoke']);
    });

    // Admin routes
    Route::middleware(['auth:device', 'can-invite'])->group(function () {
        Route::get('/invitations', [InvitationController::class, 'index']);
        Route::post('/invitations', [InvitationController::class, 'store']);
        Route::delete('/invitations/{id}', [InvitationController::class, 'revoke']);
    });
});
