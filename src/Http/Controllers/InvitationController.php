<?php

namespace Qisthidev\AuthDevice\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Qisthidev\AuthDevice\Events\InvitationAccepted;
use Qisthidev\AuthDevice\Events\InvitationRevoked;
use Qisthidev\AuthDevice\Models\Device;
use Qisthidev\AuthDevice\Models\Invitation;

class InvitationController extends Controller
{
    /**
     * List all invitations (admin).
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::guard('device')->user();

        if (! $user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if (! method_exists($user, 'invitations')) {
            return response()->json([
                'message' => 'User model does not support invitations.',
            ], 500);
        }

        $invitations = $user->invitations()->get()->map(function ($invitation) {
            return [
                'id' => $invitation->id,
                'email' => $invitation->email,
                'code' => $invitation->code,
                'status' => $invitation->status,
                'expires_at' => $invitation->expires_at,
                'accepted_at' => $invitation->accepted_at,
                'created_at' => $invitation->created_at,
            ];
        });

        return response()->json([
            'invitations' => $invitations,
        ]);
    }

    /**
     * Create a new invitation (admin).
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'metadata' => 'nullable|array',
        ]);

        $user = Auth::guard('device')->user();

        if (! $user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if (! method_exists($user, 'invite')) {
            return response()->json([
                'message' => 'User model does not support invitations.',
            ], 500);
        }

        $invitation = $user->invite(
            $request->input('email'),
            $request->input('metadata', [])
        );

        return response()->json([
            'message' => 'Invitation created successfully.',
            'invitation' => [
                'id' => $invitation->id,
                'email' => $invitation->email,
                'code' => $invitation->code,
                'expires_at' => $invitation->expires_at,
            ],
        ], 201);
    }

    /**
     * Get invitation details by code.
     */
    public function show(string $code): JsonResponse
    {
        $invitationModel = config('auth-device.models.invitation', Invitation::class);

        $invitation = $invitationModel::where('code', $code)->first();

        if (! $invitation) {
            return response()->json([
                'message' => 'Invitation not found.',
            ], 404);
        }

        if ($invitation->isExpired()) {
            return response()->json([
                'message' => 'Invitation has expired.',
            ], 410);
        }

        if (! $invitation->isPending()) {
            return response()->json([
                'message' => 'Invitation is no longer valid.',
            ], 410);
        }

        return response()->json([
            'invitation' => [
                'code' => $invitation->code,
                'email' => $invitation->email,
                'expires_at' => $invitation->expires_at,
            ],
        ]);
    }

    /**
     * Accept invitation and register device.
     */
    public function accept(Request $request, string $code): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email',
            'password' => 'nullable|string|min:8',
            'device_name' => 'required|string|max:255',
            'device_fingerprint' => 'nullable|string|max:255',
            'platform' => 'nullable|string|in:ios,android,web,desktop',
            'metadata' => 'nullable|array',
        ]);

        $invitationModel = config('auth-device.models.invitation', Invitation::class);
        $invitation = $invitationModel::where('code', $code)->first();

        if (! $invitation) {
            return response()->json([
                'message' => 'Invitation not found.',
            ], 404);
        }

        if ($invitation->isExpired()) {
            return response()->json([
                'message' => 'Invitation has expired.',
            ], 410);
        }

        if (! $invitation->isPending()) {
            return response()->json([
                'message' => 'Invitation is no longer valid.',
            ], 410);
        }

        $userModel = config('auth-device.models.user', 'App\\Models\\User');

        // Find or create the user
        $user = $userModel::where('email', $invitation->email)->first();

        if (! $user) {
            // Create new user
            $user = $userModel::create([
                'name' => $request->input('name'),
                'email' => $invitation->email,
                'password' => $request->has('password')
                    ? Hash::make($request->input('password'))
                    : Hash::make(str()->random(32)),
            ]);
        }

        // Register the device
        $deviceToken = Device::generateToken();

        $device = Device::create([
            'user_id' => $user->id,
            'name' => $request->input('device_name'),
            'device_token' => $deviceToken,
            'device_fingerprint' => $request->input('device_fingerprint'),
            'platform' => $request->input('platform', 'web'),
            'last_used_at' => now(),
            'last_ip_address' => $request->ip(),
            'is_active' => true,
            'verified_at' => config('auth-device.require_device_verification') ? null : now(),
            'expires_at' => Device::calculateExpiresAt(),
            'metadata' => $request->input('metadata'),
        ]);

        // Accept the invitation
        $invitation->accept();

        event(new InvitationAccepted($invitation, $user));

        return response()->json([
            'message' => 'Invitation accepted successfully.',
            'user' => $user,
            'device' => [
                'id' => $device->id,
                'name' => $device->name,
                'platform' => $device->platform,
            ],
            'token' => $deviceToken,
        ], 201);
    }

    /**
     * Revoke invitation (admin).
     */
    public function revoke(Request $request, int $id): JsonResponse
    {
        $user = Auth::guard('device')->user();

        if (! $user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if (! method_exists($user, 'invitations')) {
            return response()->json([
                'message' => 'User model does not support invitations.',
            ], 500);
        }

        $invitation = $user->invitations()->find($id);

        if (! $invitation) {
            return response()->json([
                'message' => 'Invitation not found.',
            ], 404);
        }

        $invitation->revoke();
        event(new InvitationRevoked($invitation, $user));

        return response()->json([
            'message' => 'Invitation revoked successfully.',
        ]);
    }
}
