@extends('layouts.app')

@section('body-class', 'auth-page')

@section('content')
<div class="container">
    <h2>عضویت جدید</h2>

    <form method="POST" action="{{ route('register') }}">
        @csrf

        @if($errors->any())
            <div class="form-group">
                <div style="background: var(--error-bg); color: var(--error-text); padding: 10px; border-radius: 6px; margin-bottom: 15px;">
                    <ul style="margin: 0; padding-right: 20px;">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        <div class="form-group">
            <label for="email">ایمیل (نام کاربری)</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus>
            <div class="help-text">ایمیل شما به عنوان نام کاربری برای ورود به سیستم استفاده خواهد شد</div>
            @error('email')
                <span class="invalid-feedback" role="alert">
                    <strong>{{ $message }}</strong>
                </span>
            @enderror
        </div>

        <div class="form-group">
            <label for="password">رمز عبور</label>
            <div class="password-field">
                <input id="password" type="password" name="password" required>
                <span class="password-toggle" onclick="togglePassword('password')">
                    <i id="password-icon" class="fas fa-eye"></i>
                </span>
            </div>
            <div class="help-text">حداقل 8 کاراکتر</div>
            @error('password')
                <span class="invalid-feedback" role="alert">
                    <strong>{{ $message }}</strong>
                </span>
            @enderror
        </div>

        <div class="form-group">
            <label for="password_confirmation">تکرار رمز عبور</label>
            <div class="password-field">
                <input id="password_confirmation" type="password" name="password_confirmation" required>
                <span class="password-toggle" onclick="togglePassword('password_confirmation')">
                    <i id="password_confirmation-icon" class="fas fa-eye"></i>
                </span>
            </div>
            @error('password_confirmation')
                <span class="invalid-feedback" role="alert">
                    <strong>{{ $message }}</strong>
                </span>
            @enderror
        </div>

        <button type="submit">ثبت نام</button>
        
        <div style="text-align: center; margin-top: 20px;">
            <a href="{{ route('login') }}" style="color: var(--primary-color); text-decoration: none;">
                قبلاً عضو هستید؟ ورود
            </a>
        </div>
    </form>
</div>
@endsection