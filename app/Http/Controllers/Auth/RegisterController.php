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
            'username' => 'required|string|max:255|unique:users',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            // Create user (inactive by default)
            $user = User::create([
                'username' => $request->username,
                'email' => $request->email,
                'password' => $request->password, // Will be hashed automatically
                'is_active' => false, // Require admin activation
                'email_verified_at' => now(), // Auto-verify email for demo
            ]);

            // Generate activation token
            $activationToken = $user->generateActivationToken();

            return redirect()->route('login')->with('success', 
                'حساب کاربری شما با موفقیت ایجاد شد. لطفاً منتظر تأیید مدیر سیستم باشید تا بتوانید وارد شوید.'
            );

        } catch (\Exception $e) {
            return back()->withErrors(['email' => 'خطا در ایجاد حساب کاربری.'])->withInput();
        }
    }
}