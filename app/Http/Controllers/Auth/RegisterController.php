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
                'name' => $request->email, // Use email as name for now
                'username' => $request->email, // Keep email as username for backward compatibility
                'email' => $request->email,
                'password' => $request->password, // Will be hashed automatically by cast
                'is_active' => true, // Users are active immediately
                'email_verified_at' => now(), // Skip email verification for now
            ]);

            return redirect()->route('login')->with('success', 
                'ثبت نام با موفقیت انجام شد! اکنون می‌توانید وارد شوید.'
            );

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Registration failed: ' . $e->getMessage());
            return back()->withErrors(['email' => 'خطا در ایجاد حساب کاربری: ' . $e->getMessage()])->withInput();
        }
    }
}