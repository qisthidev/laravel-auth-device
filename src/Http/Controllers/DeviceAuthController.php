<?php

namespace Qisthidev\AuthDevice\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Qisthidev\AuthDevice\Events\DeviceRevoked;
use Qisthidev\AuthDevice\Guards\DeviceGuard;
use Qisthidev\AuthDevice\Models\Device;

class DeviceAuthController extends Controller
{
    /**
     * Authenticate using device token.
     */
    public function authenticate(Request $request): JsonResponse
    {
        $request->validate([
            'device_token' => 'required|string',
        ]);

        $deviceToken = $request->input('device_token');
        $deviceModel = config('auth-device.models.device', Device::class);

        $device = $deviceModel::where('device_token', $deviceToken)
            ->active()
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->first();

        if (! $device) {
            return response()->json([
                'message' => 'Invalid device token.',
            ], 401);
        }

        $userModel = config('auth-device.models.user', 'App\\Models\\User');
        $user = $userModel::find($device->user_id);

        if (! $user) {
            return response()->json([
                'message' => 'User not found.',
            ], 404);
        }

        // Check if verification is required
        if (config('auth-device.require_device_verification') && $device->verified_at === null) {
            return response()->json([
                'message' => 'Device is not verified.',
            ], 403);
        }

        // Update last used
        $device->markAsUsed($request->ip());

        return response()->json([
            'message' => 'Authentication successful.',
            'user' => $user,
            'device' => [
                'id' => $device->id,
                'name' => $device->name,
                'platform' => $device->platform,
                'last_used_at' => $device->last_used_at,
            ],
            'token' => $device->device_token,
        ]);
    }

    /**
     * Logout (revoke current device).
     */
    public function logout(Request $request): JsonResponse
    {
        $guard = Auth::guard('device');

        if (! $guard instanceof DeviceGuard) {
            return response()->json([
                'message' => 'Invalid authentication guard.',
            ], 500);
        }

        $device = $guard->device();
        $user = $guard->user();

        if ($device && $user) {
            $device->revoke();
            event(new DeviceRevoked($device, $user));
        }

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }

    /**
     * List user's devices.
     */
    public function devices(Request $request): JsonResponse
    {
        $user = Auth::guard('device')->user();

        if (! $user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if (! method_exists($user, 'devices')) {
            return response()->json([
                'message' => 'User model does not support devices.',
            ], 500);
        }

        $devices = $user->devices()->get()->map(function ($device) {
            return [
                'id' => $device->id,
                'name' => $device->name,
                'platform' => $device->platform,
                'last_used_at' => $device->last_used_at,
                'last_ip_address' => $device->last_ip_address,
                'is_active' => $device->is_active,
                'verified_at' => $device->verified_at,
                'expires_at' => $device->expires_at,
                'created_at' => $device->created_at,
            ];
        });

        return response()->json([
            'devices' => $devices,
        ]);
    }

    /**
     * Revoke a specific device.
     */
    public function revoke(Request $request, int $id): JsonResponse
    {
        $user = Auth::guard('device')->user();

        if (! $user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if (! method_exists($user, 'devices')) {
            return response()->json([
                'message' => 'User model does not support devices.',
            ], 500);
        }

        $device = $user->devices()->find($id);

        if (! $device) {
            return response()->json([
                'message' => 'Device not found.',
            ], 404);
        }

        $device->revoke();
        event(new DeviceRevoked($device, $user));

        return response()->json([
            'message' => 'Device revoked successfully.',
        ]);
    }
}
