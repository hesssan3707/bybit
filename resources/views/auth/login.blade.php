@extends('layouts.app')

@section('body-class', 'auth-page')

@section('content')
<div class="container">
    <h2>ورود به سیستم</h2>

    <form method="POST" action="{{ route('login') }}">
        @csrf

        @error('email')
            <div class="form-group">
                <span class="invalid-feedback" role="alert" style="text-align: right;">
                    <strong>{{ $message }}</strong>
                </span>
            </div>
        @enderror

        @if(session('success'))
            <div class="form-group">
                <div style="background: #d1e7dd; color: #0f5132; padding: 10px; border-radius: 6px; text-align: center; margin-bottom: 15px;">
                    {{ session('success') }}
                </div>
            </div>
        @endif

        <div class="form-group">
            <label for="email">ایمیل</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus>
        </div>

        <div class="form-group">
            <label for="password">رمز عبور</label>
            <div class="password-field">
                <input id="password" type="password" name="password" required>
                <span class="password-toggle" onclick="togglePassword('password')">
                    <i id="password-icon" class="fas fa-eye"></i>
                </span>
            </div>
            @error('password')
                <span class="invalid-feedback" role="alert">
                    <strong>{{ $message }}</strong>
                </span>
            @enderror
        </div>

        <div class="form-group" style="display: flex; align-items: center;">
            <input type="checkbox" name="remember" id="remember" {{ old('remember') ? 'checked' : '' }} style="width: auto; margin-left: 10px;">
            <label for="remember" style="margin-bottom: 0;">
                مرا به خاطر بسپار
            </label>
        </div>

        <button type="submit">ورود</button>
        
        <div style="text-align: center; margin-top: 20px;">
            <a href="{{ route('register') }}" style="color: var(--primary-color); text-decoration: none; margin-left: 15px;">
                عضویت جدید
            </a>
            <span style="color: #ccc;">|</span>
            <a href="{{ route('password.forgot') }}" style="color: var(--primary-color); text-decoration: none; margin-right: 15px;">
                فراموشی رمز عبور
            </a>
        </div>
    </form>
</div>
@endsection
