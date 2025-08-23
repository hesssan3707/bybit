<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class RegisterController extends Controller
{
    public function __construct()
    {
        $this->middleware('guest');
    }

    /**
     * Show the registration form
     */
    public function showRegistrationForm()
    {
        return view('auth.register');
    }

    /**
     * Handle registration request
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            // Create user (active by default, email verification required)
            $user = User::create([
                'username' => $request->email, // Keep email as username for now
                'email' => $request->email,
                'password' => $request->password, // Will be hashed automatically
                'is_active' => true, // Users are active immediately
                'email_verified_at' => null, // Require email verification
            ]);

            // Generate email verification token
            $verificationToken = $user->generateEmailVerificationToken();

            // TODO: Send email verification email
            // Mail::to($user->email)->send(new EmailVerificationMail($user, $verificationToken));

            return redirect()->route('login')->with('success', 
                __('messages.registration_successful') . '! ' . __('messages.login_available_now') . ' (' . __('messages.email_verification_in_production') . ')'
            );

        } catch (\Exception $e) {
            return back()->withErrors(['email' => 'خطا در ایجاد حساب کاربری.'])->withInput();
        }
    }
}