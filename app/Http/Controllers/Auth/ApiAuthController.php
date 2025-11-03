<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserExchange;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Models\VisitorLog;

class ApiAuthController extends Controller
{
    /**
     * Handle API login and return access token
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email',
                'password' => 'required|string',
                'exchange_name' => 'required|string|in:bybit,binance,bingx', // Require exchange selection
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Find user by email
            $user = User::findByEmail($request->email);

            if (!$user || !Hash::check($request->password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => __('auth.failed')
                ], 401);
            }

            // Check if user account is active
            if (!$user->isActive()) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.account_inactive')
                ], 403);
            }

            // Block API access for users in strict mode
            if ($user->future_strict_mode) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.api_disabled_strict_mode')
                ], 403);
            }

            // For demo: Allow login without email verification
            // In production, add email verification check:
            // if (!$user->hasVerifiedEmail()) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'Please verify your email address first.'
            //     ], 403);
            // }

            // Check if user has the specified exchange activated
            $userExchange = $user->exchanges()
                ->where('exchange_name', $request->exchange_name)
                ->where('is_active', true)
                ->where('status', 'approved')
                ->first();

            if (!$userExchange) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.exchange_not_configured'),
                    'available_exchanges' => $user->activeExchanges()->pluck('exchange_name')->toArray()
                ], 403);
            }

            // Generate new API token
            $token = Str::random(80);
            $expiresAt = Carbon::now()->addDays(30); // Token expires in 30 days

            // Update user with new token and set the selected exchange as current working exchange
            $user->update([
                'api_token' => hash('sha256', $token),
                'api_token_expires_at' => $expiresAt,
                'current_exchange_id' => $userExchange->id, // Track which exchange this token is for
            ]);

            // Log API login event
            try {
                VisitorLog::create([
                    'ip' => $request->ip(),
                    'user_id' => $user->id,
                    'event_type' => 'login',
                    'route' => $request->route() ? $request->route()->getName() : 'api.auth.login',
                    'referrer' => $request->headers->get('referer'),
                    'user_agent' => $request->header('User-Agent'),
                    'occurred_at' => now(),
                ]);
            } catch (\Exception $e) {}

            return response()->json([
                'success' => true,
                'message' => 'Login successful',
                'data' => [
                    'access_token' => $token,
                    'token_type' => 'Bearer',
                    'expires_at' => $expiresAt->toISOString(),
                    'exchange' => [
                        'id' => $userExchange->id,
                        'name' => $userExchange->exchange_name,
                        'display_name' => $userExchange->exchange_display_name,
                        'color' => $userExchange->exchange_color,
                        'is_default' => $userExchange->is_default,
                    ],
                    'user' => [
                        'id' => $user->id,
                        'email' => $user->email,
                        'is_active' => $user->is_active,
                        'activated_at' => $user->activated_at,
                        'available_exchanges' => $user->activeExchanges()->pluck('exchange_name')->toArray(),
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Login failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Logout and invalidate access token
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout(Request $request)
    {
        try {
            $user = $request->user();
            
            if ($user) {
                $user->update([
                    'api_token' => null,
                    'api_token_expires_at' => null,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Logout successful'
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'User not authenticated'
            ], 401);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Logout failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get current authenticated user info
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function user(Request $request)
    {
        try {
            $user = $request->user();
            
            // Load current exchange relationship
            $user->load('currentExchange');

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'created_at' => $user->created_at,
                    'token_expires_at' => $user->api_token_expires_at,
                    'current_exchange' => $user->currentExchange ? [
                        'id' => $user->currentExchange->id,
                        'name' => $user->currentExchange->exchange_name,
                        'display_name' => $user->currentExchange->exchange_display_name,
                        'color' => $user->currentExchange->exchange_color,
                        'is_default' => $user->currentExchange->is_default,
                    ] : null,
                    'available_exchanges' => $user->activeExchanges()->pluck('exchange_name')->toArray(),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get user info: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Refresh access token
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function refresh(Request $request)
    {
        try {
            $user = $request->user();

            // Generate new API token
            $token = Str::random(80);
            $expiresAt = Carbon::now()->addDays(30);

            // Update user with new token
            $user->update([
                'api_token' => hash('sha256', $token),
                'api_token_expires_at' => $expiresAt,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Token refreshed successfully',
                'data' => [
                    'access_token' => $token,
                    'token_type' => 'Bearer',
                    'expires_at' => $expiresAt->toISOString(),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token refresh failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Register a new user (requires admin activation)
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function register(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|string|email|max:255|unique:users,email',
                'password' => 'required|string|min:8|confirmed',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Create user (active by default, email verification required)
            $user = User::create([
                'username' => $request->email, // Keep email as username for now
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'is_active' => true, // Users are active immediately
                'email_verified_at' => null, // Require email verification
            ]);

            // Log API signup event
            try {
                VisitorLog::create([
                    'ip' => $request->ip(),
                    'user_id' => $user->id,
                    'event_type' => 'signup',
                    'route' => $request->route() ? $request->route()->getName() : 'api.auth.register',
                    'referrer' => $request->headers->get('referer'),
                    'user_agent' => $request->header('User-Agent'),
                    'occurred_at' => now(),
                ]);
            } catch (\Exception $e) {}

            // Generate email verification token
            $verificationToken = $user->generateEmailVerificationToken();

            // TODO: Send email verification email
            // Mail::to($user->email)->send(new EmailVerificationMail($user, $verificationToken));

            return response()->json([
                'success' => true,
                'message' => __('messages.registration_successful') . '! ' . __('messages.login_available_now') . ' (' . __('messages.email_verification_in_production') . ')',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'email' => $user->email,
                        'is_active' => $user->is_active,
                        'email_verified' => false,
                        'created_at' => $user->created_at,
                    ],
                    'status' => 'email_verification_required',
                    'message' => 'Your account is now active and ready to use. You can start by requesting exchange activation.'
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Registration failed: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get user's available exchanges for login selection
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAvailableExchanges(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Find user by email
            $user = User::findByEmail($request->email);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            // Check if user account is active
            if (!$user->isActive()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Account is not activated. Please wait for admin approval.'
                ], 403);
            }

            // Get user's active exchanges
            $activeExchanges = $user->activeExchanges()->get()->map(function ($exchange) {
                return [
                    'name' => $exchange->exchange_name,
                    'display_name' => $exchange->exchange_display_name,
                    'color' => $exchange->exchange_color,
                    'is_default' => $exchange->is_default,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'exchanges' => $activeExchanges,
                    'all_available_exchanges' => UserExchange::getAvailableExchanges(),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get available exchanges: ' . $e->getMessage()
            ], 500);
        }
    }
}