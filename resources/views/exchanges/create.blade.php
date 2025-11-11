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
        padding: 20px;
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
        font-weight: 400;
        color: white;
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
        border-color: var(--primary-color);
        box-shadow: 0 0 8px rgba(0,123,255,0.25);
        outline: none;
    }
    .btn {
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
    .btn:hover {
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
        padding: 15px;
        margin-bottom: 20px;
    }
    .warning-box h4 {
        margin: 0 0 10px 0;
        color: #a67f06;
    }
    .warning-box ul {
        margin: 0;
        padding-right: 20px;
        color: #a67f06;
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
    /* Request Type Switch (Tabs) */
    .request-switch {
        display: flex;
        width: 100%;
        gap: 0;
        border: 1px solid var(--border-color);
        border-radius: 12px;
        overflow: hidden;
        margin-bottom: 16px;
        background: rgba(255,255,255,0.04);
        backdrop-filter: blur(6px);
    }
    .request-switch__btn {
        flex: 1;
        background: transparent;
        color: #fff;
        border: none;
        padding: 12px 16px;
        cursor: pointer;
        text-align: center;
        font-weight: 600;
        transition: background 0.2s ease, color 0.2s ease, box-shadow 0.2s ease;
    }
    .request-switch__btn + .request-switch__btn { border-right: 1px solid var(--border-color); }
    .request-switch__btn:hover { background: rgba(255,255,255,0.08); }
    .request-switch__btn.active {
        background: linear-gradient(135deg, var(--primary-color), rgba(255,255,255,0.9));
        color: #000;
        font-weight: 700;
        box-shadow: 0 4px 14px rgba(0,0,0,0.15);
    }
    .request-switch__btn:focus-visible {
        outline: none;
        box-shadow: 0 0 0 3px rgba(0,123,255,0.25);
    }
    /* Account type toggles */
    .account-type-group {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
    }
    .account-type {
        position: relative;
        display: inline-flex;
        align-items: center;
        cursor: pointer;
    }
    .account-type input {
        position: absolute;
        opacity: 0;
        pointer-events: none;
    }
    .account-type__box {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 14px;
        border: 1px solid var(--border-color);
        border-radius: 22px;
        background: #1f2937;
        color: #fff;
        transition: all 0.2s ease;
        user-select: none;
    }
    .account-type__dot {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        background: #6b7280;
        box-shadow: inset 0 0 0 2px rgba(255,255,255,0.2);
    }
    .account-type:hover .account-type__box { background: #243142; }
    .account-type input:checked + .account-type__box {
        border-color: var(--primary-color);
        background: linear-gradient(135deg, var(--primary-color), rgba(255,255,255,0.9));
        color: #000;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }
    .account-type input:checked + .account-type__box .account-type__dot {
        background: #111827;
        box-shadow: inset 0 0 0 2px rgba(0,0,0,0.2);
    }
</style>
@endpush

@section('content')
<div class="glass-card container">

    <div class="form-card">
        <h2 style="text-align: center; margin-bottom: 30px;">درخواست فعال‌سازی صرافی جدید</h2>

        <!-- تب‌های انتخاب نوع درخواست -->
        <div class="request-switch">
            <button type="button" class="request-switch__btn active" id="switch-own" onclick="switchTab('own')">اتصال با کلیدهای خود</button>
            <button type="button" class="request-switch__btn" id="switch-company" onclick="switchTab('company')">درخواست دسترسی صرافی شرکت</button>
        </div>

        @if($errors->any())
            <div class="alert alert-error">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif
        
        <!-- هشدار اختصاصی برای تب اتصال با کلیدهای خود -->
        <div id="own-warning" class="warning-box" style="display:block;">
            <h4>⚠️ نکات مهم:</h4>
            <ul>
                <li>API Key و Secret شما به صورت امن و رمزگذاری شده ذخیره می‌شود</li>
                <li>درخواست شما نیاز به تأیید مدیر سیستم دارد</li>
                <li>حتماً API Key دارای مجوزهای معاملات اسپات و فیوچرز باشد</li>
                <li>از IP WhiteList استفاده نکنید یا آدرس سرور را اضافه کنید</li>
                <li>شما می‌توانید اطلاعات حساب دمو یا واقعی یا هر دو را وارد کنید</li>
            </ul>
        </div>

        <!-- فرم اتصال با کلیدهای خود -->
        <form id="own-form" method="POST" action="{{ route('exchanges.store') }}">
            @csrf

            <!-- Exchange Selection -->
            <div class="form-group">
                <label>انتخاب صرافی:</label>
                @if(count($availableExchanges) > 0)
                    @foreach($availableExchanges as $key => $exchange)
                        <div class="exchange-option" onclick="selectExchangeOwn('{{ $key }}', '{{ $exchange['color'] }}')" id="own-exchange-{{ $key }}">
                            <div class="exchange-info">
                                <img src="{{ asset('public/logos/' . $key . '-logo.png') }}" alt="{{ subStr($exchange['name'] , 0 , 2) }}" class="exchange-logo" style="background-color: {{ $exchange['color'] }};">
                                <div>
                                    <h4 style="margin: 0;">{{ $exchange['name'] }}</h4>
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
                        <input id="api_key" type="text" name="api_key" autocomplete="off" value="{{ old('api_key') }}"
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
                            <input id="api_secret" type="password" autocomplete="off" name="api_secret" 
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

                    <!-- Demo Credentials Section -->

                    <div id="demo-credentials">
                        <div class="form-group">
                            <label for="demo_api_key">کلید API دمو (Demo API Key):</label>
                            <input id="demo_api_key" type="text" name="demo_api_key" autocomplete="off" value="{{ old('demo_api_key') }}"
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
                        <button type="button" onclick="testRealConnectionCreate()" class="btn" id="test-real-btn-create" style="margin-left: 10px; background-color: #007bff; color: white;">
                            تست اتصال حساب واقعی
                        </button>
                        
                        <button type="button" onclick="testDemoConnectionCreate()" class="btn" id="test-demo-btn-create" style="background-color: #28a745; color: white; display: none;">
                            تست اتصال حساب دمو
                        </button>
                    </div>

                    <button type="submit" class="btn">
                        ارسال درخواست فعال‌سازی
                    </button>
                </div>
            @endif
        </form>

            <!-- هشدار اختصاصی برای تب درخواست دسترسی صرافی شرکت -->
            <div id="company-warning" class="warning-box" style="display:none;border-radius:8px;">
                <h4>راهنمای درخواست دسترسی صرافی شرکت:</h4>
                <ul>
                    <li>پس از بررسی و تأیید مدیر، دسترسی حساب شرکت برای شما فعال می‌شود.</li>
                    <li>نیازی به وارد کردن API Key و Secret نیست؛ کلیدها توسط تیم شرکت ثبت می‌شود.</li>
                    <li>می‌توانید یکی یا هر دو نوع حساب (واقعی و دمو) را انتخاب کنید.</li>
                    <li>در تست لوکال، اتصال به صرافی انجام نمی‌شود و بررسی‌ها روی سرور انجام خواهد شد.</li>
                </ul>
            </div>

            <!-- فرم درخواست دسترسی صرافی شرکت -->
            <form id="company-form" method="POST" action="{{ route('exchanges.company-request.store') }}" style="display:none;">
                @csrf

            <!-- انتخاب صرافی برای درخواست شرکت -->
            <div class="form-group">
                <label>انتخاب صرافی:</label>
                @if(count($availableExchanges) > 0)
                    @foreach($availableExchanges as $key => $exchange)
                        <div class="exchange-option" onclick="selectExchangeCompany('{{ $key }}', '{{ $exchange['color'] }}')" id="company-exchange-{{ $key }}">
                            <div class="exchange-info">
                                <img src="{{ asset('public/logos/' . $key . '-logo.png') }}" alt="{{ subStr($exchange['name'] , 0 , 2) }}" class="exchange-logo" style="background-color: {{ $exchange['color'] }};">
                                <div>
                                    <h4 style="margin: 0;">{{ $exchange['name'] }}</h4>
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

            <!-- نوع حساب (دمو/واقعی) -->
            <div class="form-group">
                <label>نوع حساب (امکان انتخاب چندگانه):</label>
                <div class="account-type-group">
                    <label class="account-type">
                        <input type="checkbox" name="account_types[]" value="live">
                        <span class="account-type__box">
                            <span class="account-type__dot" aria-hidden="true"></span>
                            واقعی (Live)
                        </span>
                    </label>
                    <label class="account-type">
                        <input type="checkbox" name="account_types[]" value="demo">
                        <span class="account-type__box">
                            <span class="account-type__dot" aria-hidden="true"></span>
                            دمو (Demo)
                        </span>
                    </label>
                </div>
            </div>

            

            <button type="submit" class="btn">
                ارسال درخواست دسترسی صرافی شرکت
            </button>
        </form>
    </div>
</div>

<script>
function switchTab(type) {
    const ownForm = document.getElementById('own-form');
    const companyForm = document.getElementById('company-form');
    const tabOwn = document.getElementById('switch-own');
    const tabCompany = document.getElementById('switch-company');
    const ownWarning = document.getElementById('own-warning');
    const companyWarning = document.getElementById('company-warning');

    if (type === 'company') {
        ownForm.style.display = 'none';
        companyForm.style.display = 'block';
        tabOwn.classList.remove('active');
        tabCompany.classList.add('active');
        if (ownWarning) ownWarning.style.display = 'none';
        if (companyWarning) companyWarning.style.display = 'block';
        const credForm = document.getElementById('credentials-form');
        if (credForm) credForm.classList.add('hidden');
    } else {
        ownForm.style.display = 'block';
        companyForm.style.display = 'none';
        tabCompany.classList.remove('active');
        tabOwn.classList.add('active');
        if (ownWarning) ownWarning.style.display = 'block';
        if (companyWarning) companyWarning.style.display = 'none';
    }
}

function selectExchangeOwn(exchangeName, color) {
    // Remove previous selections
    document.querySelectorAll('[id^="own-exchange-"]').forEach(option => {
        option.classList.remove('selected');
        option.style.background = '';
        option.style.color = '';
    });

    // Select current exchange
    const selectedOption = document.getElementById(`own-exchange-${exchangeName}`);
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

function selectExchangeCompany(exchangeName, color) {
    // Remove previous selections
    document.querySelectorAll('[id^="company-exchange-"]').forEach(option => {
        option.classList.remove('selected');
        option.style.background = '';
        option.style.color = '';
    });

    // Select current exchange
    const selectedOption = document.getElementById(`company-exchange-${exchangeName}`);
    selectedOption.classList.add('selected');
    selectedOption.style.background = `linear-gradient(135deg, ${color}, rgba(255,255,255,0.9))`;
    selectedOption.style.color = 'white';

    // Check the radio button
    selectedOption.querySelector('input[type="radio"]').checked = true;

    // Update form styling to match exchange color
    document.documentElement.style.setProperty('--primary-color', color);
}

function checkDemoInputs() {
    const demoApiKey = document.getElementById('demo_api_key').value.trim();
    const demoApiSecret = document.getElementById('demo_api_secret').value.trim();
    const demoTestBtn = document.getElementById('test-demo-btn-create');
    
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

async function testRealConnectionCreate() {
    const btn = document.getElementById('test-real-btn-create');
    const originalText = btn.textContent;
    const apiKey = document.getElementById('api_key').value;
    const apiSecret = document.getElementById('api_secret').value;
    const exchangeName = document.querySelector('input[name="exchange_name"]:checked')?.value;

    if (!apiKey || !apiSecret) {
        modernAlert('لطفاً ابتدا کلید API و کلید محرمانه را وارد کنید.', 'warning', 'اطلاعات ناقص');
        return;
    }

    if (!exchangeName) {
        modernAlert('لطفاً ابتدا یک صرافی انتخاب کنید.', 'warning', 'صرافی انتخاب نشده');
        return;
    }

    btn.textContent = 'در حال تست...';
    btn.disabled = true;

    try {
        const response = await fetch('/exchanges/test-real-connection', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({
                exchange_name: exchangeName,
                api_key: apiKey,
                api_secret: apiSecret,
                is_demo: false
            })
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
        modernAlert('خطا در برقراری ارتباط با سرور', 'error', 'خطا در تست اتصال');
    }
}

async function testDemoConnectionCreate() {
    const exchangeName = document.querySelector('input[name="exchange_name"]:checked')?.value;
    const demoApiKey = document.getElementById('demo_api_key').value;
    const demoApiSecret = document.getElementById('demo_api_secret').value;
    const button = document.getElementById('test-demo-btn-create');
    const originalText = button.textContent;

    if (!exchangeName || !demoApiKey || !demoApiSecret) {
        modernAlert('لطفاً تمام فیلدهای مورد نیاز را پر کنید', 'warning', 'اطلاعات ناقص');
        return;
    }

    button.disabled = true;
    button.textContent = 'در حال تست...';

    try {
        const response = await fetch('/exchanges/test-demo-connection', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({
                exchange_name: exchangeName,
                api_key: demoApiKey,
                api_secret: demoApiSecret,
                is_demo: true
            })
        });

        const data = await response.json();

        if (data.success) {
            button.textContent = '✓ موفق';
            button.style.backgroundColor = '#28a745';
            setTimeout(() => {
                button.textContent = originalText;
                button.style.backgroundColor = '#28a745';
                button.disabled = false;
            }, 2000);
            modernAlert(data.message || 'تست اتصال حساب دمو موفق بود', 'success', 'تست اتصال موفق');
        } else {
            button.textContent = '✗ خطا';
            button.style.backgroundColor = '#dc3545';
            setTimeout(() => {
                button.textContent = originalText;
                button.style.backgroundColor = '#28a745';
                button.disabled = false;
            }, 2000);
            modernAlert(data.message || 'خطا در تست اتصال حساب دمو', 'error', 'خطا در تست اتصال');
        }
    } catch (error) {
        button.textContent = '✗ خطا';
        button.style.backgroundColor = '#dc3545';
        setTimeout(() => {
            button.textContent = originalText;
            button.style.backgroundColor = '#28a745';
            button.disabled = false;
        }, 2000);
        modernAlert('خطا در برقراری ارتباط با سرور', 'error', 'خطا در تست اتصال');
    }
}

// Check demo inputs on page load
document.addEventListener('DOMContentLoaded', function() {
    checkDemoInputs();
    switchTab('own');

    // Client-side validation: ensure at least one account type selected for company request
    const companyForm = document.getElementById('company-form');
    if (companyForm) {
        companyForm.addEventListener('submit', function(e) {
            const checkedCount = document.querySelectorAll('input[name="account_types[]"]:checked').length;
            if (checkedCount === 0) {
                e.preventDefault();
                if (typeof modernAlert === 'function') {
                    modernAlert('لطفاً حداقل یک نوع حساب (دمو یا واقعی) را انتخاب کنید.', 'warning', 'نوع حساب نامعتبر');
                } else {
                    alert('لطفاً حداقل یک نوع حساب (دمو یا واقعی) را انتخاب کنید.');
                }
            }
        });
    }
});
</script>
@endsection
