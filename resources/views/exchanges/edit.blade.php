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
        padding: 20px;
        border-radius: 15px;
        box-shadow: 0 8px 25px rgba(0,0,0,0.4);
    }
    .exchange-header {
        text-align: center;
        margin-bottom: 30px;
        padding: 20px;
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
    .exchange-details-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-bottom: 25px;
    }
    .detail-item {
        background: rgba(248, 249, 250, 0.65);
        padding: 15px;
        border-radius: 12px;
    }
    .detail-label {
        font-size: 12px;
        font-weight: 600;
        color: #666;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 5px;
    }
    .detail-value {
        font-size: 14px;
        font-weight: 500;
        color: #333;
        font-family: 'Courier New', monospace;
    }
    .form-group {
        margin-bottom: 20px;
    }
    label {
        display: block;
        margin-bottom: 8px;
        font-weight: 400;
        color: #ffffff;
    }
    input {
        width: 100%;
        padding: 12px;
        border: 1px solid var(--border-color);
        border-radius: 8px;
        font-size: 14px;
        box-sizing: border-box;
        transition: border-color 0.3s, box-shadow 0.3s;
        direction:ltr;
    }
    textarea {
        width: 100%;
        padding: 12px;
        border: 1px solid var(--border-color);
        border-radius: 8px;
        font-size: 14px;
        box-sizing: border-box;
        transition: border-color 0.3s, box-shadow 0.3s;
        direction:rtl;
    }
    input:focus, textarea:focus {
        border-color: {{ $exchange->exchange_color }};
        box-shadow: 0 0 8px rgba(0,123,255,0.25);
        outline: none;
    }
    button {
        width: 100%;
        padding: 14px;
        color: #000000;
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
<div class="glass-card container">
    <div class="form-card">
        <div class="exchange-header">
            <h2>ویرایش اطلاعات {{ $exchange->exchange_display_name }}</h2>
        </div>

        @if($errors->any())
            <div class="alert alert-error">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <div class="exchange-details-grid">
            <div class="detail-item">
                <div class="detail-label">کلید API</div>
                <div class="detail-value">{{ $exchange->masked_api_key }}</div>
            </div>
            <div class="detail-item">
                <div class="detail-label">وضعیت</div>
                <div class="detail-value"><span style="color:
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
                    </span></div>
            </div>
        </div>

        <div class="warning-box">
            <h4>⚠️ نکات مهم:</h4>
            <ul>
                <li>با تغییر اطلاعات API، صرافی غیرفعال شده و نیاز به تأیید مجدد مدیر خواهد داشت</li>
                <li>تا زمان تأیید، امکان استفاده از این صرافی وجود نخواهد داشت</li>
                <li>اطلاعات جدید به صورت امن و رمزگذاری شده ذخیره می‌شود</li>
                <li>حتماً API Key جدید دارای مجوزهای لازم باشد</li>
				<li>شما میتوانید اطلاعات حساب دمو یا واقعی یا هردو را وارد کنید</li>
            </ul>
        </div>

        <form method="POST" action="{{ route('exchanges.update', $exchange) }}">
            @csrf
            @method('PUT')

            <div class="form-group">
                <label for="api_key">کلید API جدید (API Key):</label>
                <input id="api_key" type="text" name="api_key" autocomplete="off" value="{{ old('api_key') }}" placeholder="کلید API جدید صرافی خود را وارد کنید">
                @error('api_key')
                    <span class="invalid-feedback" role="alert">
                        <strong>{{ $message }}</strong>
                    </span>
                @enderror
            </div>

            <div class="form-group">
                <label for="api_secret">کلید محرمانه جدید (API Secret):</label>
                <div class="password-field">
                    <input id="api_secret" type="password" name="api_secret" autocomplete="off" placeholder="کلید محرمانه جدید صرافی خود را وارد کنید">
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

            <!-- Demo Credentials Section -->

            <div id="demo-credentials">
                <div class="form-group">
                    <label for="demo_api_key">کلید API دمو (Demo API Key):</label>
                    <input id="demo_api_key" type="text" name="demo_api_key" autocomplete="off" value="{{ old('demo_api_key', $exchange->demo_api_key) }}"
                           placeholder="کلید API حساب دمو خود را وارد کنید" oninput="checkDemoInputs()">
                    @error('demo_api_key')
                        <span class="invalid-feedback" role="alert">
                            <strong>{{ $message }}</strong>
                        </span>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="demo_api_secret">کلید محرمانه دمو (Demo API Secret):</label>
                    <div class="password-field">
                        <input id="demo_api_secret" type="password" autocomplete="off" name="demo_api_secret"
                               placeholder="کلید محرمانه حساب دمو خود را وارد کنید" oninput="checkDemoInputs()">
                        <span class="password-toggle" onclick="togglePassword('demo_api_secret')">
                            <i id="demo_api_secret-icon" class="fas fa-eye"></i>
                        </span>
                    </div>
                    @error('demo_api_secret')
                        <span class="invalid-feedback" role="alert">
                            <strong>{{ $message }}</strong>
                        </span>
                    @enderror
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 20px;">
                <button type="button" onclick="testRealConnectionEdit()" class="btn" id="test-real-btn-edit" style="margin-left: 10px; background-color: #007bff; color: white;">
                    تست اتصال حساب واقعی
                </button>
                
                <button type="button" onclick="testDemoConnectionEdit()" class="btn" id="test-demo-btn-edit" style="background-color: #28a745; color: white; display: none;">
                    تست اتصال حساب دمو
                </button>
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

<script>
function checkDemoInputs() {
    const demoApiKey = document.getElementById('demo_api_key').value.trim();
    const demoApiSecret = document.getElementById('demo_api_secret').value.trim();
    const demoTestBtn = document.getElementById('test-demo-btn-edit');
    
    if (demoApiKey && demoApiSecret) {
        demoTestBtn.style.display = 'inline-block';
    } else {
        demoTestBtn.style.display = 'none';
    }
}

function togglePassword(fieldId) {
    const field = document.getElementById(fieldId);
    const icon = document.getElementById(fieldId + '-icon');
    
    if (field.type === 'password') {
        field.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        field.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

async function testRealConnectionEdit() {
    const btn = document.getElementById('test-real-btn-edit');
    const originalText = btn.textContent;
    const apiKey = document.getElementById('api_key').value;
    const apiSecret = document.getElementById('api_secret').value;
    const exchangeId = {{ $exchange->id }};

    if (!apiKey || !apiSecret) {
        alert('لطفاً ابتدا کلید API و کلید محرمانه را وارد کنید.');
        return;
    }

    btn.textContent = 'در حال تست...';
    btn.disabled = true;

    try {
        const response = await fetch(`/exchanges/${exchangeId}/test-real-connection`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        });

        const data = await response.json();

        if (data.success) {
            btn.textContent = '✓ موفق';
            btn.style.backgroundColor = '#28a745';
            setTimeout(() => {
                btn.textContent = originalText;
                btn.style.backgroundColor = '#007bff';
                btn.disabled = false;
            }, 2000);
        } else {
            btn.textContent = '✗ خطا';
            btn.style.backgroundColor = '#dc3545';
            setTimeout(() => {
                btn.textContent = originalText;
                btn.style.backgroundColor = '#007bff';
                btn.disabled = false;
            }, 2000);
            modernAlert(data.message || 'خطا در تست اتصال حساب واقعی', 'error', 'خطا در تست اتصال');
        }
    } catch (error) {
        btn.textContent = '✗ خطا';
        btn.style.backgroundColor = '#dc3545';
        setTimeout(() => {
            btn.textContent = originalText;
            btn.style.backgroundColor = '#007bff';
            btn.disabled = false;
        }, 2000);
        modernAlert('خطا در تست اتصال حساب واقعی', 'error', 'خطا در تست اتصال');
    }
}

async function testDemoConnectionEdit() {
    const btn = document.getElementById('test-demo-btn-edit');
    const originalText = btn.textContent;
    const demoApiKey = document.getElementById('demo_api_key').value;
    const demoApiSecret = document.getElementById('demo_api_secret').value;
    const exchangeId = {{ $exchange->id }};

    if (!demoApiKey || !demoApiSecret) {
        modernAlert('لطفاً ابتدا کلید API و کلید محرمانه دمو را وارد کنید.', 'warning', 'اطلاعات ناقص');
        return;
    }

    btn.textContent = 'در حال تست...';
    btn.disabled = true;

    try {
        const response = await fetch(`/exchanges/${exchangeId}/test-demo-connection`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        });

        const data = await response.json();

        if (data.success) {
            btn.textContent = '✓ موفق';
            btn.style.backgroundColor = '#198754';
            setTimeout(() => {
                btn.textContent = originalText;
                btn.style.backgroundColor = '#28a745';
                btn.disabled = false;
            }, 2000);
        } else {
            btn.textContent = '✗ خطا';
            btn.style.backgroundColor = '#dc3545';
            setTimeout(() => {
                btn.textContent = originalText;
                btn.style.backgroundColor = '#28a745';
                btn.disabled = false;
            }, 2000);
            modernAlert(data.message || 'خطا در تست اتصال حساب دمو', 'error', 'خطا در تست اتصال');
        }
    } catch (error) {
        btn.textContent = '✗ خطا';
        btn.style.backgroundColor = '#dc3545';
        setTimeout(() => {
            btn.textContent = originalText;
            btn.style.backgroundColor = '#28a745';
            btn.disabled = false;
        }, 2000);
        modernAlert('خطا در تست اتصال حساب دمو', 'error', 'خطا در تست اتصال');
    }
}

// Check demo inputs on page load
document.addEventListener('DOMContentLoaded', function() {
    checkDemoInputs();
});
</script>
@endsection
