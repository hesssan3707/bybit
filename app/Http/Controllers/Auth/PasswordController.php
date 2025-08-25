<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class PasswordController extends Controller
{
    /**
     * Show forgot password form
     */
    public function showForgotForm()
    {
        return view('auth.forgot-password');
    }

    /**
     * Handle forgot password request
     */
    public function forgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            $user = User::findByEmail($request->email);
            
            if (!$user) {
                return back()->withErrors(['email' => 'این ایمیل در سیستم یافت نشد.']);
            }

            // Generate password reset token
            $token = $user->generatePasswordResetToken();

            // In a real application, you would send an email here
            // For now, we'll just return the token (for demo purposes)
            // Mail::to($user->email)->send(new PasswordResetMail($user, $token));

            // For demo/testing purposes, show token in session
            session()->flash('reset_token', $token);
            session()->flash('user_email', $user->email);

            return redirect()->route('password.reset.form', ['token' => $token])
                ->with('success', 'لینک بازیابی رمز عبور ارسال شد. (در محیط تست، از لینک زیر استفاده کنید)');

        } catch (\Exception $e) {
            Log::error('Password reset failed: ' . $e->getMessage());
            return back()->withErrors(['email' => 'خطا در ارسال لینک بازیابی رمز عبور.']);
        }
    }

    /**
     * Show password reset form
     */
    public function showResetForm(Request $request, $token = null)
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->email
        ]);
    }

    /**
     * Handle password reset
     */
    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            $user = User::findByEmail($request->email);
            
            if (!$user) {
                return back()->withErrors(['email' => 'کاربری با این ایمیل یافت نشد.']);
            }

            if (!$user->verifyPasswordResetToken($request->token)) {
                return back()->withErrors(['token' => 'توکن بازیابی نامعتبر یا منقضی شده است.']);
            }

            // Reset password
            $user->resetPassword($request->password);

            return redirect()->route('login')
                ->with('success', 'رمز عبور با موفقیت تغییر یافت. اکنون می‌توانید وارد شوید.');

        } catch (\Exception $e) {
            Log::error('Password reset failed: ' . $e->getMessage());
            return back()->withErrors(['password' => 'خطا در تغییر رمز عبور.']);
        }
    }

    /**
     * Show change password form in profile
     */
    public function showChangePasswordForm()
    {
        return view('auth.change-password');
    }

    /**
     * Handle change password in profile
     */
    public function changePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required',
            'password' => 'required|min:8|confirmed|different:current_password',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator);
        }

        try {
            $user = auth()->user();

            // Verify current password
            if (!Hash::check($request->current_password, $user->password)) {
                return back()->withErrors(['current_password' => 'رمز عبور فعلی اشتباه است.']);
            }

            // Update password
            $user->update([
                'password' => $request->password
            ]);

            return redirect()->route('profile.index')
                ->with('success', 'رمز عبور با موفقیت تغییر یافت.');

        } catch (\Exception $e) {
            Log::error('Password change failed: ' . $e->getMessage());
            return back()->withErrors(['password' => 'خطا در تغییر رمز عبور.']);
        }
    }

    /**
     * API: Forgot password
     */
    public function apiForgotPassword(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email|exists:users,email',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = User::findByEmail($request->email);
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            $token = $user->generatePasswordResetToken();

            // In production, send email here
            // Mail::to($user->email)->send(new PasswordResetMail($user, $token));

            return response()->json([
                'success' => true,
                'message' => 'Password reset link sent to your email',
                'data' => [
                    'reset_token' => $token, // Remove this in production
                    'expires_at' => $user->password_reset_expires_at->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('API Password reset failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to send password reset link'
            ], 500);
        }
    }

    /**
     * API: Reset password
     */
    public function apiResetPassword(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'token' => 'required',
                'email' => 'required|email',
                'password' => 'required|min:8|confirmed',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = User::findByEmail($request->email);
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            if (!$user->verifyPasswordResetToken($request->token)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or expired reset token'
                ], 400);
            }

            $user->resetPassword($request->password);

            return response()->json([
                'success' => true,
                'message' => 'Password reset successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('API Password reset failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to reset password'
            ], 500);
        }
    }

    /**
     * API: Change password (authenticated)
     */
    public function apiChangePassword(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'current_password' => 'required',
                'password' => 'required|min:8|confirmed|different:current_password',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = $request->user();

            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Current password is incorrect'
                ], 400);
            }

            $user->update([
                'password' => $request->password
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Password changed successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('API Password change failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to change password'
            ], 500);
        }
    }
}