<?php

namespace Qisthidev\AuthDevice\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Qisthidev\AuthDevice\Guards\DeviceGuard;
use Symfony\Component\HttpFoundation\Response;

class EnsureDeviceIsValid
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $guard = Auth::guard('device');

        if (! $guard instanceof DeviceGuard) {
            return response()->json([
                'message' => 'Invalid authentication guard.',
            ], 500);
        }

        $user = $guard->user();

        if ($user === null) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $device = $guard->device();

        if ($device === null) {
            return response()->json([
                'message' => 'Device not found.',
            ], 401);
        }

        if (! $device->isActive()) {
            return response()->json([
                'message' => 'Device is not active.',
            ], 401);
        }

        if ($device->isExpired()) {
            return response()->json([
                'message' => 'Device token has expired.',
            ], 401);
        }

        // Verification check if required
        if (config('auth-device.require_device_verification') && $device->verified_at === null) {
            return response()->json([
                'message' => 'Device is not verified.',
            ], 403);
        }

        return $next($request);
    }
}
