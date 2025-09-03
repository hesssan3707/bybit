@extends('layouts.app')

@section('body-class', 'auth-page')

@section('content')
<div class="container">
    <h2>فراموشی رمز عبور</h2>

    <form method="POST" action="{{ route('password.forgot') }}">
        @csrf

        @if($errors->any())
            <div class="form-group">
                <div style="background: var(--error-bg); color: var(--error-text); padding: 10px; border-radius: 6px; margin-bottom: 15px;">
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            </div>
        @endif

        @if(session('success'))
            <div class="form-group">
                <div style="background: var(--success-bg); color: var(--success-text); padding: 10px; border-radius: 6px; margin-bottom: 15px;">
                    {{ session('success') }}
                    
                    @if(session('reset_token'))
                        <div class="token-display">
                            <strong>لینک بازیابی (محیط تست):</strong><br>
                            <a href="{{ route('password.reset.form', ['token' => session('reset_token'), 'email' => session('user_email')]) }}" 
                               style="color: var(--primary-color);">
                                کلیک کنید برای بازیابی رمز عبور
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        @endif

        <div class="form-group">
            <label for="email">ایمیل</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus>
            <div class="help-text">ایمیل حساب کاربری خود را وارد کنید</div>
            @error('email')
                <span class="invalid-feedback" role="alert">
                    <strong>{{ $message }}</strong>
                </span>
            @enderror
        </div>

        <button type="submit">ارسال لینک بازیابی</button>
        
        <div style="text-align: center; margin-top: 20px;">
            <a href="{{ route('login') }}" style="color: var(--primary-color); text-decoration: none;">
                بازگشت به صفحه ورود
            </a>
        </div>
    </form>
</div>
@endsection