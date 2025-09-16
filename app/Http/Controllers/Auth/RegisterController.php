<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ConfirmationCode;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
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
     * Show confirmation code input form
     */
    public function showConfirmationForm()
    {
        return view('auth.register_confirmation');
    }

    /**
     * Handle confirmation code verification
     */
    public function verifyConfirmation(Request $request)
    {
        $request->validate([
            'code' => 'required|digits:5',
        ]);
        $pending = session('pending_registration');
        if (!$pending) {
            return redirect()->route('register')->withErrors(['email' => 'اطلاعات ثبت نام یافت نشد.']);
        }
        $record = ConfirmationCode::where('email', $pending['email'])
            ->where('code', $request->code)
            ->where('used', false)
            ->where('expires_at', '>', now())
            ->first();
        if (!$record) {
            return back()->withErrors(['code' => 'کد تایید نامعتبر یا منقضی شده است.']);
        }
        // Mark code as used
        ConfirmationCode::where('id', $record->id)->update(['used' => true]);
        // Create user
        $user = User::create([
            'name' => $pending['email'],
            'username' => $pending['email'],
            'email' => $pending['email'],
            'password' => $pending['password'],
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        session()->forget('pending_registration');
        return redirect()->route('login')->with('success', 'ثبت نام با موفقیت انجام شد! اکنون می‌توانید وارد شوید.');
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
            // Generate 5-digit confirmation code
            $code = str_pad(random_int(0, 99999), 5, '0', STR_PAD_LEFT);
            // Save code to confirmation_codes table
            ConfirmationCode::insert([
                'email' => $request->email,
                'code' => $code,
                'used' => false,
                'expires_at' => now()->addMinutes(10),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            // Send confirmation code email
            try {
                // Send confirmation code email
                Mail::send('emails.confirmation_code', ['code' => $code], function ($message) use ($request) {
                    $message->to($request->email)->subject('کد تایید ثبت نام');
                });
            } catch (\Exception $e) {
                return redirect()->back()->withInput()->withErrors(['email' => 'ارسال ایمیل تایید با مشکل مواجه شد. لطفاً بعداً دوباره تلاش کنید یا با پشتیبانی تماس بگیرید.']);
            }
            // Store registration data in session for confirmation step
            session(['pending_registration' => [
                'email' => $request->email,
                'password' => $request->password,
            ]]);
            return redirect()->route('register.confirmation')->with('success', 'کد تایید به ایمیل شما ارسال شد. لطفاً کد را وارد کنید.');
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Registration failed: ' . $e->getMessage());
            return back()->withErrors(['email' => 'خطا در ایجاد حساب کاربری: ' . $e->getMessage()])->withInput();
        }
    }
}
