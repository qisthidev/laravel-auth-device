<?php

namespace Qisthidev\AuthDevice\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Qisthidev\AuthDevice\Traits\CanInvite;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserCanInvite
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('device')->user();

        if ($user === null) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        // Check if the user model uses the CanInvite trait
        $traits = class_uses_recursive($user);

        if (! in_array(CanInvite::class, $traits)) {
            return response()->json([
                'message' => 'User does not have permission to invite.',
            ], 403);
        }

        // Optional: Check for a specific permission or role
        // This can be customized by extending this middleware
        if (method_exists($user, 'canInvite') && ! $user->canInvite()) {
            return response()->json([
                'message' => 'User does not have permission to invite.',
            ], 403);
        }

        return $next($request);
    }
}
