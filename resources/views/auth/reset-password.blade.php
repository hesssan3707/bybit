@extends('layouts.app')

@section('body-class', 'auth-page')

@section('content')
<div class="container">
    <h2>بازیابی رمز عبور</h2>

    <form method="POST" action="{{ route('password.reset') }}">
        @csrf

        <input type="hidden" name="token" value="{{ $token }}">

        @if(session('success'))
            <div class="alert alert-success">
                {{ session('success') }}
            </div>
        @endif

        @if($errors->any())
            <div class="alert alert-error">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <div class="form-group">
            <label for="email">ایمیل</label>
            <input id="email" type="email" name="email" value="{{ old('email', $email) }}" required autofocus>
            @error('email')
                <span class="invalid-feedback" role="alert">
                    <strong>{{ $message }}</strong>
                </span>
            @enderror
        </div>

        <div class="form-group">
            <label for="password">رمز عبور جدید</label>
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

        <div class="form-group">
            <label for="password_confirmation">تکرار رمز عبور جدید</label>
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

        <button type="submit">
            تغییر رمز عبور
        </button>

        <div class="links">
            <a href="{{ route('login') }}">بازگشت به صفحه ورود</a>
        </div>
    </form>
</div>
@endsection