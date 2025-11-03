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

    public function showLoginForm(Request $request)
    {
        // Log visit and detect first-time IP to auto-show info modal
        try {
            $ip = $request->ip();
            $ua = $request->header('User-Agent');
            $referrer = $request->headers->get('referer');
            $routeName = $request->route() ? $request->route()->getName() : 'login';

            $hasVisit = \App\Models\VisitorLog::where('ip', $ip)
                ->where('event_type', 'visit')
                ->exists();

            if (!$hasVisit) {
                \App\Models\VisitorLog::create([
                    'ip' => $ip,
                    'user_id' => null,
                    'event_type' => 'visit',
                    'route' => $routeName,
                    'referrer' => $referrer,
                    'user_agent' => $ua,
                    'occurred_at' => now(),
                ]);
            }

            return view('auth.login', ['showInfoModal' => !$hasVisit]);
        } catch (\Exception $e) {
            return view('auth.login');
        }
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
                'email' => [__('auth.failed')],
            ]);
        }

        // Check if account is active (email verification handled separately for demo)
        if (!$user->isActive()) {
            throw ValidationException::withMessages([
                'email' => [__('messages.account_inactive')],
            ]);
        }

        // For demo: Allow login without email verification
        // In production, add email verification check:
        // if (!$user->hasVerifiedEmail()) {
        //     throw ValidationException::withMessages([
        //         'email' => ['لطفاً ابتدا ایمیل خود را تأیید کنید.'],
        //     ]);
        // }

        // Login the user
        Auth::login($user, $request->filled('remember'));
        $request->session()->regenerate();

        // Log successful login
        try {
            \App\Models\VisitorLog::create([
                'ip' => $request->ip(),
                'user_id' => $user->id,
                'event_type' => 'login',
                'route' => $request->route() ? $request->route()->getName() : 'login',
                'referrer' => $request->headers->get('referer'),
                'user_agent' => $request->header('User-Agent'),
                'occurred_at' => now(),
            ]);
        } catch (\Exception $e) {}
        
        return redirect()->intended(route('futures.orders'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/');
    }
}
