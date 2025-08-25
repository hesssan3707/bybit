@extends('layouts.app')

@section('title', 'ویرایش اطلاعات صرافی')

@push('styles')
<style>
    .container {
        width: 100%;
        max-width: 600px;
        margin: auto;
    }
    .form-card {
        background: #ffffff;
        padding: 30px;
        border-radius: 15px;
        box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        border-left: 5px solid {{ $exchange->exchange_color }};
    }
    .exchange-header {
        text-align: center;
        margin-bottom: 30px;
        padding: 20px;
        background: linear-gradient(135deg, {{ $exchange->exchange_color }}, rgba(255,255,255,0.1));
        border-radius: 10px;
        color: white;
    }
    .exchange-logo {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        margin: 0 auto 15px auto;
        background: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        font-size: 24px;
        color: {{ $exchange->exchange_color }};
    }
    .form-group {
        margin-bottom: 20px;
    }
    label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: var(--label-color);
    }
    input, textarea {
        width: 100%;
        padding: 12px;
        border: 1px solid var(--border-color);
        border-radius: 8px;
        font-size: 14px;
        box-sizing: border-box;
        transition: border-color 0.3s, box-shadow 0.3s;
    }
    input:focus, textarea:focus {
        border-color: {{ $exchange->exchange_color }};
        box-shadow: 0 0 8px rgba(0,123,255,0.25);
        outline: none;
    }
    button {
        width: 100%;
        padding: 14px;
        background: {{ $exchange->exchange_color }};
        color: white;
        border: none;
        border-radius: 8px;
        font-size: 16px;
        font-weight: bold;
        margin-top: 10px;
        cursor: pointer;
        transition: opacity 0.3s;
    }
    button:hover {
        opacity: 0.9;
    }
    .invalid-feedback {
        color: var(--error-text);
        font-size: 14px;
        margin-top: 5px;
        display: block;
    }
    .alert {
        padding: 12px 16px;
        border-radius: 8px;
        margin-bottom: 20px;
        text-align: center;
    }
    .alert-error {
        background-color: #f8d7da;
        color: #842029;
        border: 1px solid #f5c2c7;
    }
    .back-link {
        display: inline-block;
        margin-bottom: 20px;
        color: {{ $exchange->exchange_color }};
        text-decoration: none;
        font-weight: 500;
    }
    .back-link:hover {
        text-decoration: underline;
    }
    .warning-box {
        background: #fff3cd;
        border: 1px solid #ffc107;
        border-left: 4px solid #ffc107;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 20px;
    }
    .warning-box h4 {
        margin: 0 0 10px 0;
        color: #856404;
    }
    .warning-box ul {
        margin: 0;
        padding-right: 20px;
        color: #856404;
    }
    .current-info {
        background: #f8f9fa;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 20px;
    }
    .masked-key {
        font-family: monospace;
        background: #e9ecef;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 12px;
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
        color: {{ $exchange->exchange_color }};
    }
</style>
@endpush

@section('content')
<div class="container">
    <a href="{{ route('exchanges.index') }}" class="back-link">← بازگشت به لیست صرافی‌ها</a>
    
    <div class="form-card">
        <div class="exchange-header">
            <div class="exchange-logo">
                {{ substr($exchange->exchange_display_name, 0, 2) }}
            </div>
            <h2>ویرایش اطلاعات {{ $exchange->exchange_display_name }}</h2>
        </div>

        @if($errors->any())
            <div class="alert alert-error">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <div class="current-info">
            <h4>اطلاعات فعلی:</h4>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 10px;">
                <div>
                    <strong>کلید API فعلی:</strong>
                    <div class="masked-key">{{ $exchange->masked_api_key }}</div>
                </div>
                <div>
                    <strong>وضعیت:</strong>
                    <span style="color: 
                        @if($exchange->is_active) #28a745 
                        @elseif($exchange->status === 'pending') #ffc107 
                        @else #dc3545 @endif">
                        @if($exchange->is_active)
                            فعال
                        @elseif($exchange->status === 'pending')
                            در انتظار تأیید
                        @else
                            {{ $exchange->status === 'rejected' ? 'رد شده' : 'غیرفعال' }}
                        @endif
                    </span>
                </div>
            </div>
        </div>

        <div class="warning-box">
            <h4>⚠️ نکات مهم:</h4>
            <ul>
                <li>با تغییر اطلاعات API، صرافی غیرفعال شده و نیاز به تأیید مجدد مدیر خواهد داشت</li>
                <li>تا زمان تأیید، امکان استفاده از این صرافی وجود نخواهد داشت</li>
                <li>اطلاعات جدید به صورت امن و رمزگذاری شده ذخیره می‌شود</li>
                <li>حتماً API Key جدید دارای مجوزهای لازم باشد</li>
            </ul>
        </div>

        <form method="POST" action="{{ route('exchanges.update', $exchange) }}">
            @csrf
            @method('PUT')

            <div class="form-group">
                <label for="api_key">کلید API جدید (API Key):</label>
                <input id="api_key" type="text" name="api_key" value="{{ old('api_key') }}" required 
                       placeholder="کلید API جدید خود را وارد کنید">
                @error('api_key')
                    <span class="invalid-feedback" role="alert">
                        <strong>{{ $message }}</strong>
                    </span>
                @enderror
            </div>

            <div class="form-group">
                <label for="api_secret">کلید محرمانه جدید (API Secret):</label>
                <div class="password-field">
                    <input id="api_secret" type="password" name="api_secret" required 
                           placeholder="کلید محرمانه جدید خود را وارد کنید">
                    <span class="password-toggle" onclick="togglePassword('api_secret')">
                        <i id="api_secret-icon" class="fas fa-eye"></i>
                    </span>
                </div>
                @error('api_secret')
                    <span class="invalid-feedback" role="alert">
                        <strong>{{ $message }}</strong>
                    </span>
                @enderror
            </div>

            <div class="form-group">
                <label for="reason">دلیل تغییر (اختیاری):</label>
                <textarea id="reason" name="reason" rows="3" 
                          placeholder="دلیل تغییر اطلاعات را بنویسید...">{{ old('reason') }}</textarea>
                @error('reason')
                    <span class="invalid-feedback" role="alert">
                        <strong>{{ $message }}</strong>
                    </span>
                @enderror
            </div>

            <button type="submit">
                ارسال درخواست به‌روزرسانی
            </button>
        </form>
    </div>
</div>
@endsection