<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserExchange;
use App\Services\Exchanges\ExchangeFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class UserManagementController extends Controller
{
    /**
     * Display list of pending users awaiting activation
     */
    public function pendingUsers()
    {
        $pendingUsers = User::inactive()
            ->latest('created_at')
            ->paginate(20);

        return view('admin.pending-users', compact('pendingUsers'));
    }

    /**
     * Display list of all users
     */
    public function allUsers()
    {
        $users = User::latest('created_at')
            ->paginate(20);

        return view('admin.all-users', compact('users'));
    }

    /**
     * Activate a user account
     */
    public function activateUser(Request $request, User $user)
    {
        try {
            if ($user->is_active) {
                return back()->withErrors(['msg' => 'این کاربر قبلاً فعال شده است.']);
            }

            $user->activate(auth()->id());

            return back()->with('success', "حساب کاربری {$user->email} با موفقیت فعال شد.");

        } catch (\Exception $e) {
            Log::error('User activation failed: ' . $e->getMessage());
            return back()->withErrors(['msg' => 'خطا در فعال‌سازی حساب کاربری.']);
        }
    }

    /**
     * Deactivate a user account
     */
    public function deactivateUser(Request $request, User $user)
    {
        try {
            if (!$user->is_active) {
                return back()->withErrors(['msg' => 'این کاربر قبلاً غیرفعال شده است.']);
            }

            // Don't allow deactivating yourself
            if ($user->id === auth()->id()) {
                return back()->withErrors(['msg' => 'شما نمی‌توانید حساب خود را غیرفعال کنید.']);
            }

            $user->update([
                'is_active' => false,
                'activated_at' => null,
                'activated_by' => null,
            ]);

            return back()->with('success', "حساب کاربری {$user->email} غیرفعال شد.");

        } catch (\Exception $e) {
            Log::error('User deactivation failed: ' . $e->getMessage());
            return back()->withErrors(['msg' => 'خطا در غیرفعال‌سازی حساب کاربری.']);
        }
    }

    /**
     * Delete a user account
     */
    public function deleteUser(Request $request, User $user)
    {
        try {
            // Don't allow deleting yourself
            if ($user->id === auth()->id()) {
                return back()->withErrors(['msg' => 'شما نمی‌توانید حساب خود را حذف کنید.']);
            }

            // Check if user has orders or trades
            $hasData = $user->orders()->exists() || 
                      $user->spotOrders()->exists() || 
                      $user->trades()->exists();

            if ($hasData) {
                return back()->withErrors(['msg' => 'این کاربر دارای سفارش یا معامله است و قابل حذف نیست.']);
            }

            $email = $user->email;
            $user->delete();

            return back()->with('success', "حساب کاربری {$email} حذف شد.");

        } catch (\Exception $e) {
            Log::error('User deletion failed: ' . $e->getMessage());
            return back()->withErrors(['msg' => 'خطا در حذف حساب کاربری.']);
        }
    }

    /**
     * API: Get pending users
     */
    public function apiPendingUsers(Request $request)
    {
        try {
            $limit = $request->input('limit', 20);
            
            $pendingUsers = User::inactive()
                ->select(['id', 'username', 'email', 'created_at'])
                ->latest('created_at')
                ->limit($limit)
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Pending users retrieved successfully',
                'data' => $pendingUsers
            ]);

        } catch (\Exception $e) {
            Log::error('API Get pending users failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get pending users'
            ], 500);
        }
    }

    /**
     * API: Activate user
     */
    public function apiActivateUser(Request $request, User $user)
    {
        try {
            if ($user->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'User is already active'
                ], 400);
            }

            $user->activate($request->user()->id);

            return response()->json([
                'success' => true,
                'message' => 'User activated successfully',
                'data' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'is_active' => $user->is_active,
                    'activated_at' => $user->activated_at,
                    'activated_by' => $user->activated_by
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('API User activation failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to activate user'
            ], 500);
        }
    }

    /**
     * API: Deactivate user
     */
    public function apiDeactivateUser(Request $request, User $user)
    {
        try {
            if (!$user->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'User is already inactive'
                ], 400);
            }

            // Don't allow deactivating yourself
            if ($user->id === $request->user()->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot deactivate your own account'
                ], 400);
            }

            $user->update([
                'is_active' => false,
                'activated_at' => null,
                'activated_by' => null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'User deactivated successfully',
                'data' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'is_active' => $user->is_active
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('API User deactivation failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to deactivate user'
            ], 500);
        }
    }

    /**
     * Check if current user is admin (for demo, you can implement proper role system)
     */
    private function isAdmin()
    {
        // For demo purposes, first user is admin
        // In production, implement proper role-based system
        return auth()->id() === 1;
    }

    /**
     * Exchange Management Methods
     */
    
    /**
     * Display list of pending exchange activation requests
     */
    public function pendingExchanges()
    {
        $pendingExchanges = UserExchange::with(['user', 'activatedBy'])
            ->pending()
            ->latest('activation_requested_at')
            ->paginate(20);

        return view('admin.pending-exchanges', compact('pendingExchanges'));
    }

    /**
     * Display list of all user exchanges
     */
    public function allExchanges()
    {
        $exchanges = UserExchange::with(['user', 'activatedBy', 'deactivatedBy'])
            ->latest('created_at')
            ->paginate(20);

        return view('admin.all-exchanges', compact('exchanges'));
    }

    /**
     * Approve exchange activation request
     */
    public function approveExchange(Request $request, UserExchange $exchange)
    {
        try {
            if ($exchange->status !== 'pending') {
                return back()->withErrors(['msg' => 'این درخواست قبلاً بررسی شده است.']);
            }

            $validator = Validator::make($request->all(), [
                'admin_notes' => 'nullable|string|max:1000',
            ]);

            if ($validator->fails()) {
                return back()->withErrors($validator);
            }

            $exchange->activate(auth()->id(), $request->admin_notes);

            return back()->with('success', "درخواست فعال‌سازی صرافی {$exchange->exchange_display_name} برای کاربر {$exchange->user->email} تأیید شد.");

        } catch (\Exception $e) {
            Log::error('Exchange approval failed: ' . $e->getMessage());
            return back()->withErrors(['msg' => 'خطا در تأیید درخواست.']);
        }
    }

    /**
     * Reject exchange activation request
     */
    public function rejectExchange(Request $request, UserExchange $exchange)
    {
        try {
            if ($exchange->status !== 'pending') {
                return back()->withErrors(['msg' => 'این درخواست قبلاً بررسی شده است.']);
            }

            $validator = Validator::make($request->all(), [
                'admin_notes' => 'required|string|max:1000',
            ]);

            if ($validator->fails()) {
                return back()->withErrors($validator);
            }

            $exchange->reject(auth()->id(), $request->admin_notes);

            return back()->with('success', "درخواست فعال‌سازی صرافی {$exchange->exchange_display_name} برای کاربر {$exchange->user->email} رد شد.");

        } catch (\Exception $e) {
            Log::error('Exchange rejection failed: ' . $e->getMessage());
            return back()->withErrors(['msg' => 'خطا در رد درخواست.']);
        }
    }

    /**
     * Deactivate an active exchange
     */
    public function deactivateExchange(Request $request, UserExchange $exchange)
    {
        try {
            if (!$exchange->is_active) {
                return back()->withErrors(['msg' => 'این صرافی قبلاً غیرفعال شده است.']);
            }

            $validator = Validator::make($request->all(), [
                'admin_notes' => 'required|string|max:1000',
            ]);

            if ($validator->fails()) {
                return back()->withErrors($validator);
            }

            $exchange->deactivate(auth()->id(), $request->admin_notes);

            return back()->with('success', "صرافی {$exchange->exchange_display_name} برای کاربر {$exchange->user->email} غیرفعال شد.");

        } catch (\Exception $e) {
            Log::error('Exchange deactivation failed: ' . $e->getMessage());
            return back()->withErrors(['msg' => 'خطا در غیرفعال‌سازی صرافی.']);
        }
    }

    /**
     * Middleware to check admin access
     */
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if (!$this->isAdmin()) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Unauthorized. Admin access required.'
                    ], 403);
                }
                abort(403, 'دسترسی مجاز نیست. نیاز به دسترسی مدیر.');
            }
            return $next($request);
        });
    }

    /**
     * Test exchange connection and validate API access
     */
    public function testExchangeConnection(Request $request, UserExchange $exchange)
    {
        try {
            if ($exchange->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'تست اتصال فقط برای درخواست‌های در انتظار امکان‌پذیر است.'
                ], 400);
            }

            // Create exchange service instance
            $exchangeService = ExchangeFactory::create(
                $exchange->exchange_name,
                $exchange->api_key,
                $exchange->api_secret
            );

            // Run comprehensive validation
            $validation = $exchangeService->validateAPIAccess();
            
            // Prepare response with Persian messages
            $responseMessage = '';
            $validationDetails = [];
            
            // Check IP access
            if (!$validation['ip']['success']) {
                $responseMessage = 'آدرس IP سرور در لیست مجاز کلید API قرار ندارد';
                $validationDetails['ip'] = [
                    'status' => 'blocked',
                    'message' => $validation['ip']['message']
                ];
            } else {
                $validationDetails['ip'] = [
                    'status' => 'allowed',
                    'message' => 'آدرس IP مجاز است'
                ];
            }
            
            // Check spot access
            if (!$validation['spot']['success']) {
                if ($validation['spot']['details']['error_type'] === 'not_supported') {
                    $validationDetails['spot'] = [
                        'status' => 'not_supported',
                        'message' => 'این صرافی از معاملات اسپات پشتیبانی نمی‌کند'
                    ];
                } else {
                    $validationDetails['spot'] = [
                        'status' => 'denied',
                        'message' => 'کلید API مجوز معاملات اسپات ندارد'
                    ];
                }
            } else {
                $validationDetails['spot'] = [
                    'status' => 'allowed',
                    'message' => 'دسترسی به معاملات اسپات تأیید شد'
                ];
            }
            
            // Check futures access
            if (!$validation['futures']['success']) {
                if ($validation['futures']['details']['error_type'] === 'not_supported') {
                    $validationDetails['futures'] = [
                        'status' => 'not_supported',
                        'message' => 'این صرافی از معاملات آتی پشتیبانی نمی‌کند'
                    ];
                } else {
                    $validationDetails['futures'] = [
                        'status' => 'denied',
                        'message' => 'کلید API مجوز معاملات آتی ندارد'
                    ];
                }
            } else {
                $validationDetails['futures'] = [
                    'status' => 'allowed',
                    'message' => 'دسترسی به معاملات آتی تأیید شد'
                ];
            }
            
            // Determine overall status and recommendation
            $overallSuccess = $validation['overall'];
            $hasAnyTrading = $validation['spot']['success'] || $validation['futures']['success'];
            
            if (!$validation['ip']['success']) {
                $recommendation = 'reject';
                $responseMessage = 'توصیه: این درخواست را رد کنید. آدرس IP سرور در لیست مجاز نیست.';
            } elseif (!$hasAnyTrading) {
                $recommendation = 'reject';
                $responseMessage = 'توصیه: این درخواست را رد کنید. کلید API هیچ مجوز معاملاتی ندارد.';
            } elseif ($validation['spot']['success'] && $validation['futures']['success']) {
                $recommendation = 'approve';
                $responseMessage = 'توصیه: این درخواست را تأیید کنید. تمام دسترسی‌ها موجود است.';
            } else {
                $recommendation = 'approve_with_warning';
                $limitedAccess = $validation['spot']['success'] ? 'فقط معاملات اسپات' : 'فقط معاملات آتی';
                $responseMessage = "توصیه: می‌توانید تأیید کنید اما کاربر {$limitedAccess} خواهد داشت.";
            }
            
            return response()->json([
                'success' => true,
                'overall_success' => $overallSuccess,
                'recommendation' => $recommendation,
                'message' => $responseMessage,
                'details' => $validationDetails,
                'raw_validation' => $validation // for debugging
            ]);
            
        } catch (\Exception $e) {
            Log::error('Exchange connection test failed: ' . $e->getMessage(), [
                'exchange_id' => $exchange->id,
                'exchange_name' => $exchange->exchange_name,
                'user_id' => $exchange->user_id
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'خطا در تست اتصال: ' . $e->getMessage(),
                'recommendation' => 'manual_review',
                'details' => [
                    'error' => $e->getMessage()
                ]
            ], 500);
        }
    }
}