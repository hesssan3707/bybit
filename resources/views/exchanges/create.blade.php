@extends('layouts.app')

@section('title', 'افزودن صرافی جدید')

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
    }
    .exchange-option {
        border: 2px solid #e9ecef;
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 15px;
        cursor: pointer;
        transition: all 0.3s;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .exchange-option:hover {
        border-color: var(--primary-color);
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(0,123,255,0.15);
    }
    .exchange-option.selected {
        border-color: var(--primary-color);
        background: linear-gradient(135deg, var(--primary-color), rgba(255,255,255,0.9));
        color: white;
    }
    .exchange-info {
        display: flex;
        align-items: center;
    }
    .exchange-logo {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        margin-left: 15px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        font-size: 18px;
        color: white;
    }
    .form-group {
        margin-bottom: 20px;
    }
    .form-group.hidden {
        display: none;
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
        direction:ltr;
    }
    input:focus, textarea:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 8px rgba(0,123,255,0.25);
        outline: none;
    }
    button {
        width: 100%;
        padding: 14px;
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
        color: var(--primary-color);
        text-decoration: none;
        font-weight: 500;
    }
    .back-link:hover {
        text-decoration: underline;
    }
    .warning-box {
        background: #fff3cd;
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
</style>
@endpush

@section('content')
<div class="container">

    <div class="form-card">
        <h2 style="text-align: center; margin-bottom: 30px;">درخواست فعال‌سازی صرافی جدید</h2>

        @if($errors->any())
            <div class="alert alert-error">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <div class="warning-box">
            <h4>⚠️ نکات مهم:</h4>
            <ul>
                <li>API Key و Secret شما به صورت امن و رمزگذاری شده ذخیره می‌شود</li>
                <li>درخواست شما نیاز به تأیید مدیر سیستم دارد</li>
                <li>حتماً API Key دارای مجوزهای معاملات اسپات و فیوچرز باشد</li>
                <li>از IP WhiteList استفاده نکنید یا آدرس سرور را اضافه کنید</li>
            </ul>
        </div>

        <form method="POST" action="{{ route('exchanges.store') }}">
            @csrf

            <!-- Exchange Selection -->
            <div class="form-group">
                <label>انتخاب صرافی:</label>
                @if(count($availableExchanges) > 0)
                    @foreach($availableExchanges as $key => $exchange)
                        <div class="exchange-option" onclick="selectExchange('{{ $key }}', '{{ $exchange['color'] }}')" id="exchange-{{ $key }}">
                            <div class="exchange-info">
                                <div class="exchange-logo" style="background-color: {{ $exchange['color'] }}">
                                    {{ substr($exchange['name'], 0, 2) }}
                                </div>
                                <div>
                                    <h4 style="margin: 0;">{{ $exchange['name'] }}</h4>
                                    <small style="color: #666;">{{ $exchange['color'] }}</small>
                                </div>
                            </div>
                            <div>
                                <input type="radio" name="exchange_name" value="{{ $key }}" style="width: auto;" required>
                            </div>
                        </div>
                    @endforeach
                @else
                    <div style="text-align: center; padding: 20px; color: #666;">
                        همه صرافی‌های موجود قبلاً اضافه شده‌اند
                    </div>
                @endif
            </div>

            @if(count($availableExchanges) > 0)
                <!-- API Credentials Form -->
                <div id="credentials-form" class="form-group hidden">
                    <div class="form-group">
                        <label for="api_key">کلید API (API Key):</label>
                        <input id="api_key" type="text" name="api_key" autocomplete="off" value="{{ old('api_key') }}" required
                               placeholder="مثال: K2IS7FEKFM3G15T4VXVUHY75QCGN4ZT6LEZJ">
                        @error('api_key')
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label for="api_secret">کلید محرمانه (API Secret):</label>
                        <div class="password-field">
                            <input id="api_secret" type="password" autocomplete="off" name="api_secret" required
                                   placeholder="کلید محرمانه صرافی خود را وارد کنید">
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
                        <label for="reason">دلیل درخواست (اختیاری):</label>
                        <textarea id="reason" name="reason" rows="3"
                                  placeholder="دلیل خود برای استفاده از این صرافی را بنویسید...">{{ old('reason') }}</textarea>
                        @error('reason')
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                        @enderror
                    </div>

                    <button type="submit">
                        ارسال درخواست فعال‌سازی
                    </button>
                </div>
            @endif
        </form>
    </div>
</div>

<script>
function selectExchange(exchangeName, color) {
    // Remove previous selections
    document.querySelectorAll('.exchange-option').forEach(option => {
        option.classList.remove('selected');
        option.style.background = '';
        option.style.color = '';
    });

    // Select current exchange
    const selectedOption = document.getElementById(`exchange-${exchangeName}`);
    selectedOption.classList.add('selected');
    selectedOption.style.background = `linear-gradient(135deg, ${color}, rgba(255,255,255,0.9))`;
    selectedOption.style.color = 'white';

    // Check the radio button
    selectedOption.querySelector('input[type="radio"]').checked = true;

    // Show credentials form
    document.getElementById('credentials-form').classList.remove('hidden');

    // Update form styling to match exchange color
    document.documentElement.style.setProperty('--primary-color', color);
}
</script>
@endsection
