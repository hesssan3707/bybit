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
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .settings-card h2 {
            margin-bottom: 30px;
            text-align: center;
            color: white;
        }
        .setting-item {
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .setting-description {
            color: #ffffff;
            font-size: 0.95em;
            line-height: 1.5;
            margin-bottom: 15px;
        }
        .warning-box {
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
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
        .btn {
            display: inline-block;
            padding: 12px 20px;
            margin: 5px;
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
            transition: opacity 0.3s;
            border: none;
            cursor: pointer;
            font-size: 14px;
        }
        .btn:hover {
            opacity: 0.8;
        }
        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }
        .btn-danger {
            background-color: #dc3545;
            color: white;
        }
        .btn:disabled {
            background-color: #6c757d;
            cursor: not-allowed;
            opacity: 0.6;
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

        /* Back to profile button styling */
        .back-to-profile {
            text-align: center;
            margin-top: 30px;
            padding: 0 15px;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        .modal-content {
            background-color: #fefefe;
            margin: 15% auto;
            padding: 30px;
            border-radius: 15px;
            width: 90%;
            max-width: 500px;
            text-align: center;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        }
        .modal-title {
            font-size: 1.3em;
            font-weight: bold;
            margin-bottom: 20px;
            color: #dc3545;
        }
        .modal-text {
            margin-bottom: 25px;
            color: #333;
            line-height: 1.6;
        }
        .modal-buttons {
            display: flex;
            justify-content: center;
            gap: 15px;
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .container {
                padding: 10px;
                margin: 0;
                width: 100%;
                max-width: 100%;
                box-sizing: border-box;
            }
            .setting-item {
                padding: 15px;
                border-radius: 8px;
                margin-bottom: 15px;
                box-sizing: border-box;
            }
            .settings-card {
                padding: 15px;
                margin: 0 0 15px 0;
                width: 100%;
                box-sizing: border-box;
            }
            .setting-header {
                flex-direction: column;
                text-align: center;
            }
            .setting-title {
                margin-bottom: 10px;
            }
            .modal-content {
                margin: 5% auto;
                width: 95%;
                padding: 15px;
                max-height: 90vh;
                overflow-y: auto;
            }
            .modal-title {
                font-size: 1.1em;
            }
            .modal-text {
                font-size: 14px;
            }
            .modal-buttons {
                flex-direction: column;
                gap: 10px;
            }
            .modal-buttons .btn {
                width: 100%;
                margin: 0;
            }
            .button-group {
                flex-direction: column;
                gap: 10px;
            }
            .btn {
                width: 100%;
                margin: 0;
                box-sizing: border-box;
            }
            .input-group {
                flex-direction: column;
                align-items: stretch;
                gap: 8px;
            }
            .form-control {
                width: 100%;
                box-sizing: border-box;
                font-size: 16px; /* Prevents zoom on iOS */
            }
            .input-suffix {
                text-align: center;
                margin-top: 5px;
            }
        }
        .modal-buttons {
            flex-direction: column;
        }
        .btn {
            width: 100%;
            margin: 5px 0;
        }

        .back-to-profile {
            margin-top: 20px;
            padding: 0 10px;
        }

        .back-to-profile .btn {
            width: calc(100% - 40px);
            max-width: 300px;
            margin: 0 auto;
            display: block;
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
        }
        .warning-box h4 {
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

        /* Modal Styles */
        .modal {
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(5px);
        }

        .modal-content {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            margin: 5% auto;
            padding: 0;
            border: 1px solid #444;
            border-radius: 15px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            animation: modalSlideIn 0.3s ease-out;
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-header {
            padding: 20px;
            border-bottom: 1px solid #444;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            margin: 0;
            color: #ffffff;
            font-size: 1.2em;
        }

        .close {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            line-height: 1;
        }

        .close:hover,
        .close:focus {
            color: #fff;
            text-decoration: none;
        }

        .modal-body {
            padding: 20px;
            color: #ffffff;
        }

        .modal-body .alert-warning {
            background: rgba(255, 193, 7, 0.2);
            border: 1px solid #ffc107;
            color: #ffc107;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 15px;
        }

        .modal-body .form-group {
            margin-bottom: 15px;
        }

        .modal-body label {
            display: block;
            margin-bottom: 5px;
            color: #ffffff;
            font-weight: 600;
        }

        .modal-body select {
            width: 100%;
            padding: 10px;
            border: 1px solid #444;
            border-radius: 8px;
            background: #2a2a3e;
            color: #ffffff;
            font-size: 14px;
        }

        .modal-body select:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.25);
        }

        .confirmation-text {
            margin-top: 15px;
            padding: 15px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 8px;
        }

        .confirmation-text ul {
            margin: 10px 0 0 20px;
            padding: 0;
        }

        .confirmation-text li {
            margin-bottom: 5px;
            color: #cccccc;
        }

        .modal-footer {
            padding: 20px;
            border-top: 1px solid #444;
        }

        .modal-buttons {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }

        /* Mobile Responsive for Modal */
        @media (max-width: 768px) {
            .modal-content {
                margin: 10% auto;
                width: 95%;
                max-height: 90vh;
                overflow-y: auto;
            }

            .modal-header h3 {
                font-size: 1.1em;
            }

            .modal-buttons {
                flex-direction: column;
                gap: 10px;
            }

            .modal-buttons .btn {
                width: 100%;
                margin: 0;
            }

            .close {
                font-size: 24px;
            }
        }

        @media (max-width: 480px) {
            .modal-content {
                margin: 5% auto;
                width: 98%;
            }

            .modal-header,
            .modal-body,
            .modal-footer {
                padding: 15px;
            }
        }
    </style>
@endpush

@include('partials.alert-modal')

@section('content')
    <div class="container glass-card">
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

                <div class="setting-item">
                    <div class="form-group">
                        <label for="default_risk">ریسک پیش‌فرض (درصد)</label>
                        <div class="input-group">
                            <input type="text"
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
                            <small style="color: #aaa;">در حالت سخت‌گیرانه حداکثر 10 درصد مجاز است</small>
                        @endif
                    </div>

                    <div class="form-group">
                        <label for="default_expiration_time">زمان انقضای پیش‌فرض (دقیقه)</label>
                        <div class="input-group">
                            <input type="text"
                                   id="default_expiration_time"
                                   name="default_expiration_time"
                                   class="form-control"
                                   value="{{ $defaultExpirationTime }}"
                                   min="1"
                                   max="1000"
                                   placeholder="خالی بگذارید برای عدم تنظیم">
                            <span class="input-suffix">دقیقه</span>
                        </div>
                        <small style="color: #aaa;">حداکثر 999 دقیقه - خالی بگذارید اگر نمی‌خواهید زمان انقضا تنظیم شود</small>
                    </div>

                    <div class="form-group">
                        <label for="default_expiration_time">تعداد پله پیش‌فرض (ثبت معامله اتی)</label>
                        <div class="input-group">
                            <input type="text"
                                   id="default_future_order_steps"
                                   name="default_future_order_steps"
                                   class="form-control"
                                   value="{{ $defaultFutureOrderSteps }}"
                                   min="1"
                                   max="8"
                                   placeholder="خالی بگذارید برای عدم تنظیم">
                            <span class="input-suffix">عدد</span>
                        </div>
                        <small style="color: #aaa;">حداکثر 8 پله - خالی بگذارید اگر نمی‌خواهید تنظیم شود</small>
                    </div>

                    <div class="form-group">
                        <label for="tv_default_interval">بازه زمانی پیش‌فرض نمودار (TradingView)</label>
                        <div class="input-group">
                            <select id="tv_default_interval" class="form-control">
                                <option value="1">۱ دقیقه</option>
                                <option value="5">۵ دقیقه</option>
                                <option value="15">۱۵ دقیقه</option>
                                <option value="60">۱ ساعت</option>
                                <option value="240">۴ ساعت</option>
                                <option value="D">۱ روز</option>
                            </select>
                        </div>
                        <small style="color: #aaa;">این تنظیم فقط نمایش نمودار را تغییر می‌دهد و در منطق سفارش اثری ندارد</small>
                    </div>

                    <div class="button-group">
                        <button type="submit" class="btn btn-primary">ذخیره تنظیمات</button>
                    </div>
                </div>
            </form>
        </div>
        <div class="settings-card">
            <h2>حالت سخت‌گیرانه آتی (Future Strict Mode) </h2>

            <div id="alert-container"></div>

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

            <!-- Future Strict Mode Setting -->
            <div class="setting-item">

                <div class="setting-description">
                    این حالت زمانی که فعال شود، موارد زیر را تحت تأثیر قرار می‌دهد(این محدودیت ها در جهت نظم دادن به معامله و جلو گیری از معامله های شتاب زده است):
                    <ul style="margin-top: 10px; padding-right: 20px;">
                        <li>امکان حذف یا تغییر Stop Loss و Take Profit وجود نخواهد داشت</li>
                        <li>تنها از طریق این سیستم می‌توانید سفارش جدید ثبت کنید</li>
                        <li>در صورت بستن سفارش فعال از طریق صرافی(اگر در نقطه حدضرر یا حد سود نباشد) ،امکان ثبت سفارش تا 3 روز اینده نخواهید داشت</li>
                        <li>درصورت بستن سفارش فعال ،امکان بستن سفارش تا یک هفته اینده را نخواهید داشت</li>
                        <li>حداکثر ریسک هر پوزیشن 10 درصد خواهد بود</li>
                        <li>پس از ضرر، باید 1 ساعت صبر کنید تا بتوانید سفارش جدید ثبت کنید</li>
                        <li>اگر دوبار پشت سرهم ضرر کنید ، تا 24 ساعت اینده نمی توانید سفارش جدید ثبت کنید</li>
                        <li><strong>باید یک بازار انتخاب کنید و تنها در همان بازار قابلیت معامله خواهید داشت</strong></li>
                        <li>این حالت پس از فعال‌سازی قابل غیرفعال‌سازی نیست</li>
                    </ul>
                </div>

                @if($user->future_strict_mode)
                    <div class="alert alert-success">
                        <strong>حالت سخت‌گیرانه آتی فعال است</strong><br>
                        تاریخ فعال‌سازی: {{ $user->future_strict_mode_activated_at->format('Y/m/d H:i') }}<br>
                        @if($user->selected_market)
                            <strong>بازار انتخابی: {{ $user->selected_market }}</strong>
                        @endif
                        @if(isset($minRrRatio))
                            <br><strong>حداقل نسبت سود به ضرر: {{ $minRrRatio }}</strong>
                        @endif
                    </div>
                @else
                    <div class="warning-box">
                        <h4>⚠️ هشدار مهم:</h4>
                        <ul>
                            <li>این حالت پس از فعال‌سازی قابل بازگشت نیست</li>
                            <li>فقط در صورت اطمینان کامل این گزینه را فعال کنید</li>
                            <li>این حالت بر معاملات اسپات تأثیری ندارد</li>
                        </ul>
                    </div>

                    <button type="button" class="btn btn-danger" onclick="showConfirmationModal()">
                        فعال‌سازی حالت سخت‌گیرانه آتی
                    </button>
                @endif
            </div>

            <!-- Back to Profile Button -->
            <div class="back-to-profile">
                <a href="{{ route('profile.index') }}" class="btn btn-primary">
                    بازگشت به پروفایل
                </a>
            </div>
        </div>
    </div>

    <!-- Confirmation Modal for Strict Mode Activation -->
    <div id="confirmationModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>تأیید فعال‌سازی حالت سخت‌گیرانه</h3>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning">
                    <strong>هشدار:</strong> این عمل غیرقابل بازگشت است!
                </div>

                <p>لطفاً بازار مورد نظر خود را انتخاب کنید. پس از فعال‌سازی، تنها می‌توانید در این بازار معامله کنید:</p>

                <div class="form-group">
                    <label for="selectedMarket">انتخاب بازار:</label>
                    <select id="selectedMarket" class="form-control" required>
                        <option value="">-- انتخاب کنید --</option>
                        <option value="BTCUSDT">BTC/USDT</option>
                        <option value="ETHUSDT">ETH/USDT</option>
                        <option value="ADAUSDT">ADA/USDT</option>
                        <option value="DOTUSDT">DOT/USDT</option>
                        <option value="BNBUSDT">BNB/USDT</option>
                        <option value="XRPUSDT">XRP/USDT</option>
                        <option value="SOLUSDT">SOL/USDT</option>
                        <option value="TRXUSDT">TRX/USDT</option>
                        <option value="DOGEUSDT">DOGE/USDT</option>
                        <option value="LTCUSDT">LTC/USDT</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="minRrRatio">حداقل نسبت سود به ضرر (RR) در حالت سخت‌گیرانه:</label>
                    <select id="minRrRatio" class="form-control">
                        <option value="3:1" selected>3:1 (سود سه برابر ضرر)</option>
                        <option value="2:1">2:1 (سود دو برابر ضرر)</option>
                        <option value="1:1">1:1 (سود برابر ضرر)</option>
                        <option value="1:2">1:2 (سود نصف ضرر)</option>
                    </select>
                </div>

                <div class="confirmation-text">
                    <p><strong>با فعال‌سازی این حالت:</strong></p>
                    <ul>
                        <li>حداکثر ریسک هر معامله به ۱۰ درصد محدود می‌شود</li>
                        <li>تنها در بازار انتخابی می‌توانید معامله کنید</li>
                        <li>این تنظیمات غیرقابل تغییر خواهد بود</li>
                    </ul>
                </div>
            </div>
            <div class="modal-footer">
                <div class="modal-buttons">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">انصراف</button>
                    <button type="button" class="btn btn-danger" onclick="activateFutureStrictMode()">تأیید و فعال‌سازی</button>
                </div>
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

            // Initialize TradingView default interval select from localStorage
            const tvIntervalSelect = document.getElementById('tv_default_interval');
            if (tvIntervalSelect) {
                const allowed = ['1','5','15','60','240','D'];
                const stored = localStorage.getItem('tv_default_interval');
                const initial = allowed.includes(stored) ? stored : '5';
                tvIntervalSelect.value = initial;
                tvIntervalSelect.addEventListener('change', function() {
                    const val = this.value;
                    if (allowed.includes(val)) {
                        localStorage.setItem('tv_default_interval', val);
                    }
                });
            }
        });

        function showConfirmationModal() {
            document.getElementById('confirmationModal').style.display = 'block';
        }

        function closeModal() {
            document.getElementById('confirmationModal').style.display = 'none';
        }

        function activateFutureStrictMode() {
            // Validate market selection
            const selectedMarket = document.getElementById('selectedMarket').value;
            const minRrRatioEl = document.getElementById('minRrRatio');
            const minRrRatio = minRrRatioEl ? minRrRatioEl.value || '3:1' : '3:1';
            if (!selectedMarket) {
                showAlert('لطفاً ابتدا بازار مورد نظر را انتخاب کنید', 'danger');
                return;
            }

            console.log('Activating strict mode with market:', selectedMarket);

            // Show loading state
            const button = document.querySelector('.modal-buttons .btn-danger');
            const originalText = button.textContent;
            button.textContent = 'در حال پردازش...';
            button.disabled = true;

            // Prepare request data
            const requestData = {
                selected_market: selectedMarket,
                min_rr_ratio: minRrRatio
            };

            console.log('Sending request data:', requestData);
            console.log('CSRF Token:', '{{ csrf_token() }}');
            console.log('Route URL:', '{{ route("settings.activate-future-strict-mode") }}');

            fetch('{{ route("settings.activate-future-strict-mode") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(requestData)
            })
                .then(response => {
                    console.log('Response status:', response.status);
                    console.log('Response headers:', response.headers);

                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }

                    return response.json();
                })
                .then(data => {
                    console.log('Response data:', data);

                    if (data.success) {
                        // Show success message and reload page
                        showAlert(data.message, 'success');
                        setTimeout(() => {
                            window.location.reload();
                        }, 3000);
                    } else {
                        // Show error message
                        showAlert(data.message || 'خطای نامشخص رخ داده است', 'danger');
                        button.textContent = originalText;
                        button.disabled = false;
                    }
                    closeModal();
                })
                .catch(error => {
                    console.error('Fetch error details:', error);
                    console.error('Error stack:', error.stack);

                    let errorMessage = 'خطا در برقراری ارتباط با سرور';

                    if (error.message.includes('HTTP error')) {
                        errorMessage = 'خطا در پردازش درخواست. لطفاً دوباره تلاش کنید.';
                    } else if (error.message.includes('Failed to fetch')) {
                        errorMessage = 'خطا در اتصال به سرور. لطفاً اتصال اینترنت خود را بررسی کنید.';
                    }

                    showAlert(errorMessage, 'danger');
                    button.textContent = originalText;
                    button.disabled = false;
                    closeModal();
                });
        }

        function showAlert(message, type) {
            const alertContainer = document.getElementById('alert-container');
            const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';

            alertContainer.innerHTML = `
                <div class="alert ${alertClass}">
                    ${message}
                </div>
                `;

            // Auto-hide after 5 seconds
            setTimeout(() => {
                alertContainer.innerHTML = '';
            }, 5000);
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('confirmationModal');
            if (event.target === modal) {
                closeModal();
            }
        }



    </script>
@endpush
