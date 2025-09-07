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
        background: rgba(255,255,255,0.08);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        padding: 30px;
        border-radius: 15px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.25);
        animation: fadeIn 0.5s ease-out;
    }
    .password-card h2 {
        text-align: center;
        margin-bottom: 25px;
        color: #ece8e8;
    }
    .form-group {
        margin-bottom: 20px;
    }
    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: #c5bfbf;
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
        direction: ltr;
    }
    .form-group input:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 8px rgba(0,123,255,0.25);
        outline: none;
    }
    .btn {
        display: inline-block;
        padding: 12px 20px;
        margin: 5px 0;
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
    .btn-primary:hover {
        opacity: 0.9;
    }
    .btn-secondary {
        background-color: #6c757d;
        color:black;
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
        text-align: right;
        direction:rtl;
    }
    .alert-success { background: rgba(34,197,94,0.12); color: #22c55e; border: 1px solid rgba(34,197,94,0.25); }
    .alert-danger { background: rgba(239,68,68,0.12); color: #ef4444; border: 1px solid rgba(239,68,68,0.25); }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(6px);} to { opacity: 1; transform: translateY(0);} }

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

@push('scripts')
<script>
// Auto-dismiss alerts
(function(){
    const onReady = () => {
        const alerts = document.querySelectorAll('.alert.auto-dismiss');
        alerts.forEach(function(el){
            setTimeout(() => { el.classList.add('fade-out'); setTimeout(() => el.remove(), 400); }, 4000);
        });
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', onReady); else onReady();
})();
</script>


@section('content')
<div class="container">
    <div class="password-card">
        <h2>تغییر رمز عبور</h2>

        @if(session('success'))
            <div class="alert alert-success auto-dismiss">
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
            </div>

            <div class="form-group">
                <label for="password">رمز عبور جدید</label>
                <div class="password-field">
                    <input id="password" type="password" name="password" required>
                    <span class="password-toggle" onclick="togglePassword('password')">
                        <i id="password-icon" class="fas fa-eye"></i>
                    </span>
                </div>
            </div>

            <div class="form-group">
                <label for="password_confirmation">تکرار رمز عبور جدید</label>
                <div class="password-field">
                    <input id="password_confirmation" type="password" name="password_confirmation" required>
                    <span class="password-toggle" onclick="togglePassword('password_confirmation')">
                        <i id="password_confirmation-icon" class="fas fa-eye"></i>
                    </span>
                </div>
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
