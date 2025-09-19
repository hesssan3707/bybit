@extends('layouts.app')

@section('title', 'تنظیمات حساب کاربری')

@push('styles')
    <style>
        .container {
            width: 100%;
            max-width: 800px;
            margin: auto;
        }
        .settings-card {
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            background: var(--card-bg);
        }
        .settings-card h2 {
            margin-bottom: 30px;
            text-align: center;
            color: var(--primary-color);
        }
        .setting-item {
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            background: var(--secondary-bg);
        }
        .setting-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        .setting-title {
            font-size: 1.2em;
            font-weight: bold;
            color: #ffffff;
        }
        .setting-description {
            color: #ffffff;
            font-size: 0.95em;
            line-height: 1.5;
            margin-bottom: 15px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #ffffff;
            font-weight: 500;
        }
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            background: var(--input-bg);
            color: #ffffff;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(255, 193, 7, 0.1);
        }
        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        .btn-primary {
            background: var(--primary-color);
            color: #000;
        }
        .btn-primary:hover {
            background: #e6b800;
            transform: translateY(-2px);
        }
        .btn-secondary {
            background: var(--secondary-color);
            color: #ffffff;
        }
        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }
        .btn-danger {
            background: #dc3545;
            color: #ffffff;
        }
        .btn-danger:hover {
            background: #c82333;
            transform: translateY(-2px);
        }
        .btn-success {
            background: #28a745;
            color: #ffffff;
        }
        .btn-success:hover {
            background: #218838;
            transform: translateY(-2px);
        }
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        .alert-success {
            background: rgba(40, 167, 69, 0.1);
            border: 1px solid #28a745;
            color: #28a745;
        }
        .alert-danger {
            background: rgba(220, 53, 69, 0.1);
            border: 1px solid #dc3545;
            color: #dc3545;
        }
        .warning-box {
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            background: rgba(255, 193, 7, 0.1);
            border: 1px solid var(--primary-color);
        }
        .warning-box h4 {
            color: var(--primary-color);
            margin-bottom: 10px;
        }
        .warning-box p {
            color: #ffffff;
            margin: 0;
        }
        .strict-mode-status {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.9em;
            font-weight: 600;
        }
        .strict-mode-active {
            background: rgba(40, 167, 69, 0.2);
            color: #28a745;
        }
        .strict-mode-inactive {
            background: rgba(220, 53, 69, 0.2);
            color: #dc3545;
        }
        .button-group {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .input-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .input-suffix {
            color: #ffffff;
            font-size: 14px;
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }
            .settings-card {
                padding: 20px;
                margin-bottom: 15px;
            }
            .setting-item {
                padding: 15px;
            }
            .button-group {
                flex-direction: column;
            }
            .btn {
                width: 100%;
                margin-bottom: 10px;
            }
            .input-group {
                flex-direction: column;
                align-items: stretch;
            }
        }
    </style>
@endpush

@section('content')
<div class="container">
    <!-- Success/Error Messages -->
    @if(session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger">
            {{ session('error') }}
        </div>
    @endif

    <!-- Account Settings Card -->
    <div class="settings-card">
        <h2>تنظیمات حساب کاربری</h2>

        <!-- Default Settings Form -->
        <form action="{{ route('account-settings.update') }}" method="POST">
            @csrf
            @method('PUT')

            <div class="setting-item">
                <div class="setting-header">
                    <div class="setting-title">تنظیمات پیش‌فرض معاملات</div>
                </div>
                
                <div class="setting-description">
                    این تنظیمات به عنوان مقادیر پیش‌فرض در فرم‌های جدید معامله استفاده خواهند شد.
                </div>

                <div class="form-group">
                    <label for="default_risk">ریسک پیش‌فرض (درصد)</label>
                    <div class="input-group">
                        <input type="number" 
                               id="default_risk" 
                               name="default_risk" 
                               class="form-control" 
                               value="{{ $defaultRisk }}" 
                               min="1" 
                               max="{{ $user->future_strict_mode ? '10' : '80' }}" 
                               step="0.01"
                               placeholder="مثال: 2.5">
                        <span class="input-suffix">%</span>
                    </div>
                    @if($user->future_strict_mode)
                        <small style="color: var(--primary-color);">در حالت سخت‌گیرانه حداکثر ۱۰ درصد مجاز است</small>
                    @endif
                </div>

                <div class="form-group">
                    <label for="default_expiration_time">زمان انقضای پیش‌فرض (دقیقه)</label>
                    <div class="input-group">
                        <input type="number" 
                               id="default_expiration_time" 
                               name="default_expiration_time" 
                               class="form-control" 
                               value="{{ $defaultExpirationTime }}" 
                               min="1" 
                               max="1000"
                               placeholder="خالی بگذارید برای عدم تنظیم">
                        <span class="input-suffix">دقیقه</span>
                    </div>
                    <small style="color: #aaa;">حداکثر ۱۰۰۰ دقیقه - خالی بگذارید اگر نمی‌خواهید زمان انقضا تنظیم شود</small>
                </div>

                <div class="button-group">
                    <button type="submit" class="btn btn-primary">ذخیره تنظیمات</button>
                    <a href="{{ route('account-settings.reset') }}" class="btn btn-secondary" 
                       onclick="return confirm('آیا مطمئن هستید که می‌خواهید تنظیمات را به حالت پیش‌فرض بازگردانید؟')">
                        بازگردانی به پیش‌فرض
                    </a>
                </div>
            </div>
        </form>

        <!-- Strict Mode Settings -->
        <div class="setting-item">
            <div class="setting-header">
                <div class="setting-title">حالت سخت‌گیرانه آتی</div>
                <span class="strict-mode-status {{ $user->future_strict_mode ? 'strict-mode-active' : 'strict-mode-inactive' }}">
                    {{ $user->future_strict_mode ? 'فعال' : 'غیرفعال' }}
                </span>
            </div>
            
            <div class="setting-description">
                در حالت سخت‌گیرانه، ریسک معاملات محدود به حداکثر ۱۰ درصد خواهد بود و امکان انجام معاملات پرریسک وجود نخواهد داشت.
            </div>

            @if($user->future_strict_mode && $defaultRisk > 10)
                <div class="warning-box">
                    <h4>هشدار</h4>
                    <p>ریسک پیش‌فرض فعلی شما ({{ $defaultRisk }}%) بیش از حد مجاز در حالت سخت‌گیرانه است. لطفاً ابتدا آن را کاهش دهید.</p>
                </div>
            @endif

            <form action="{{ route('account-settings.strict-mode') }}" method="POST" style="display: inline;">
                @csrf
                @method('PUT')
                
                @if($user->future_strict_mode)
                    <input type="hidden" name="future_strict_mode" value="0">
                    <button type="submit" class="btn btn-danger">غیرفعال کردن حالت سخت‌گیرانه</button>
                @else
                    <input type="hidden" name="future_strict_mode" value="1">
                    <button type="submit" class="btn btn-success" 
                            @if($defaultRisk > 10) 
                                onclick="return confirm('ریسک پیش‌فرض شما بیش از ۱۰ درصد است. آیا مطمئن هستید؟')" 
                            @endif>
                        فعال کردن حالت سخت‌گیرانه
                    </button>
                @endif
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // Update max risk value when strict mode changes
    document.addEventListener('DOMContentLoaded', function() {
        const riskInput = document.getElementById('default_risk');
        const strictMode = {{ $user->future_strict_mode ? 'true' : 'false' }};
        
        if (strictMode) {
            riskInput.max = '10';
        }
        
        // Validate risk input in real-time
        riskInput.addEventListener('input', function() {
            const value = parseFloat(this.value);
            if (strictMode && value > 10) {
                this.setCustomValidity('در حالت سخت‌گیرانه حداکثر ۱۰ درصد مجاز است');
            } else {
                this.setCustomValidity('');
            }
        });
    });
</script>
@endpush