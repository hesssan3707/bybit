<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
    }

    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email', // Changed from username to email
            'password' => 'required|string',
        ]);

        // Find user by email
        $user = User::findByEmail($request->email);
        
        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['نام کاربری یا رمز عبور اشتباه است.'],
            ]);
        }

        // Check if account is active
        if (!$user->isActive()) {
            throw ValidationException::withMessages([
                'email' => ['حساب کاربری شما هنوز فعال نشده است. لطفاً منتظر تأیید مدیر باشید.'],
            ]);
        }

        // Login the user
        Auth::login($user, $request->filled('remember'));
        $request->session()->regenerate();
        
        return redirect()->intended(route('orders.index'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/');
    }
}
