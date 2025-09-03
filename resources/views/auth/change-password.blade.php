@extends('layouts.app')

@section('title', 'تغییر رمز عبور')

@push('styles')
<style>
    .container {
        width: 100%;
        max-width: 500px;
        margin: 20px auto;
        padding: 0 15px;
    }
    .password-card {
        background: #ffffff;
        padding: 30px;
        border-radius: 15px;
        box-shadow: 0 8px 25px rgba(0,0,0,0.1);
    }
    .password-card h2 {
        text-align: center;
        margin-bottom: 25px;
        color: var(--primary-color);
    }
    .form-group {
        margin-bottom: 20px;
    }
    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: #555;
        text-align: right;
    }
    .form-group input {
        width: 100%;
        padding: 12px;
        border: 1px solid #ccc;
        border-radius: 8px;
        font-size: 14px;
        box-sizing: border-box;
        transition: border-color 0.3s, box-shadow 0.3s;
        direction: rtl;
    }
    .form-group input:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 8px rgba(0,123,255,0.25);
        outline: none;
    }
    .btn {
        display: inline-block;
        padding: 12px 20px;
        margin: 5px;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        font-weight: bold;
        text-decoration: none;
        text-align: center;
        transition: all 0.3s;
        width: 100%;
        box-sizing: border-box;
    }
    .btn-primary {
        background: linear-gradient(90deg, var(--primary-color), var(--primary-hover));
        color: white;
    }
    .btn-primary:hover {
        opacity: 0.9;
    }
    .btn-secondary {
        background-color: #6c757d;
        color: white;
        margin-top: 10px;
    }
    .btn-secondary:hover {
        background-color: #5a6268;
        color: white;
        text-decoration: none;
    }
    .invalid-feedback {
        color: #dc3545;
        font-size: 14px;
        margin-top: 5px;
        display: block;
        text-align: right;
    }
    .alert {
        padding: 12px 16px;
        border-radius: 8px;
        margin-bottom: 20px;
        text-align: center;
    }
    .alert-success {
        background-color: #d1e7dd;
        color: #0f5132;
        border: 1px solid #badbcc;
    }
    .alert-danger {
        background-color: #f8d7da;
        color: #842029;
        border: 1px solid #f5c2c7;
    }
    
    .password-field {
        position: relative;
    }
    .password-toggle {
        position: absolute;
        right: 12px;
        top: 50%;
        transform: translateY(-50%);
        cursor: pointer;
        color: #666;
        font-size: 18px;
        user-select: none;
    }
    .password-toggle:hover {
        color: var(--primary-color);
    }
    
    /* Mobile Responsive Styles */
    @media (max-width: 768px) {
        .container {
            margin: 10px;
            padding: 0;
            width: calc(100% - 20px);
        }
        
        .password-card {
            padding: 15px;
            margin: 0;
        }
        
        .password-card h2 {
            font-size: 1.3em;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group input {
            padding: 12px;
            font-size: 16px; /* Prevent zoom on iOS */
        }
        
        .btn {
            padding: 14px 15px;
            font-size: 16px;
            margin: 5px 0;
        }
    }
    
    @media (max-width: 480px) {
        .container {
            margin: 5px;
            width: calc(100% - 10px);
        }
        
        .password-card {
            padding: 12px;
        }
        
        .password-card h2 {
            font-size: 1.2em;
        }
        
        .form-group input {
            padding: 10px;
        }
    }
</style>
@endpush

@section('content')
<div class="container auth-page">
    <div class="password-card">
        <h2>تغییر رمز عبور</h2>

        @if(session('success'))
            <div class="alert alert-success">
                {{ session('success') }}
            </div>
        @endif

        @if($errors->any())
            <div class="alert alert-danger">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('password.change') }}">
            @csrf

            <div class="form-group">
                <label for="current_password">رمز عبور فعلی</label>
                <div class="password-field">
                    <input id="current_password" type="password" name="current_password" required autofocus>
                    <span class="password-toggle" onclick="togglePassword('current_password')">
                        <i id="current_password-icon" class="fas fa-eye"></i>
                    </span>
                </div>
                @error('current_password')
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

            <button type="submit" class="btn btn-primary">
                تغییر رمز عبور
            </button>
            
            <a href="{{ route('profile.index') }}" class="btn btn-secondary">
                بازگشت به پروفایل
            </a>
        </form>
    </div>
</div>
@endsection