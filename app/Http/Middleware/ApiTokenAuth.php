<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\User;
use Symfony\Component\HttpFoundation\Response;

class ApiTokenAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $this->getTokenFromRequest($request);

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Access token is required'
            ], 401);
        }

        $user = User::findByToken($token);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired access token'
            ], 401);
        }

        // Block API access for users in strict mode
        if ($user->future_strict_mode) {
            return response()->json([
                'success' => false,
                'message' => __('messages.api_disabled_strict_mode')
            ], 403);
        }

        // Set the authenticated user for this request
        $request->setUserResolver(function () use ($user) {
            return $user;
        });
        
        // Also set the user in the auth guard so auth()->user() works
        auth()->setUser($user);

        // Load current exchange for API context
        $user->load('currentExchange');

        // Add user and current exchange to request for easy access
        $request->merge([
            'authenticated_user' => $user,
            'current_exchange' => $user->currentExchange
        ]);

        return $next($request);
    }

    /**
     * Extract token from the request
     */
    private function getTokenFromRequest(Request $request)
    {
        // Check Authorization header
        $authHeader = $request->header('Authorization');
        if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
            return substr($authHeader, 7);
        }

        // Check query parameter as fallback
        return $request->query('access_token');
    }
}