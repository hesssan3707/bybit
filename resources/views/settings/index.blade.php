@extends('layouts.app')

@section('title', 'تنظیمات')

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
        .btn-success {
            background-color: #28a745;
            color: white;
        }
        .btn:disabled {
            background-color: #6c757d;
            cursor: not-allowed;
            opacity: 0.6;
        }
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .status-active {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .status-inactive {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
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
                padding: 0;
                margin: 0 auto;
                width: 100%;
            }
            .setting-item {
                padding: 0;
                border-radius: 0;
                margin-bottom: 20px;
            }
            .settings-card {
                padding: 20px;
                margin: 10px;
                width: calc(100% - 20px);
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
            .form-control {
                font-size: 16px; /* Prevents zoom on iOS */
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
    </style>
@endpush

@section('content')
    <div class="glass-card container">
        <div class="settings-card">
            <h2>تنظیمات حساب کاربری</h2>

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
                <div class="setting-header">
                    <div class="setting-title">حالت سخت‌گیرانه آتی (Future Strict Mode)</div>
                    <div>
                        @if($user->future_strict_mode)
                            <span class="status-badge status-active">فعال</span>
                        @else
                            <span class="status-badge status-inactive">غیرفعال</span>
                        @endif
                    </div>
                </div>

                <div class="setting-description">
                    این حالت زمانی که فعال شود، موارد زیر را تحت تأثیر قرار می‌دهد:
                    <ul style="margin-top: 10px; padding-right: 20px;">
                        <li>برای پشتیبانی از چند پوزیشن همزمان ،نوع حساب شما به Hedge Mode تغییر خواهد کرد</li>
                        <li>امکان حذف یا تغییر Stop Loss و Take Profit وجود نخواهد داشت</li>
                        <li>تنها از طریق این سیستم می‌توانید سفارش جدید ثبت کنید</li>
                        <li>حداکثر ریسک هر پوزیشن 10 درصد خواهد بود</li>
                        <li>پس از ضرر، باید 1 ساعت صبر کنید تا بتوانید سفارش جدید ثبت کنید</li>
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

    <!-- Confirmation Modal -->
    <div id="confirmationModal" class="modal">
        <div class="modal-content">
            <div class="modal-title">تأیید فعال‌سازی حالت سخت‌گیرانه آتی</div>
            <div class="modal-text">
                <p><strong>آیا از فعال‌سازی حالت سخت‌گیرانه آتی اطمینان دارید؟</strong></p>
                <p>توجه: این عمل غیرقابل بازگشت است و پس از فعال‌سازی امکان غیرفعال‌سازی وجود ندارد.</p>
                <p>پس از فعال‌سازی، حساب شما تحت نظارت دقیق قرار گرفته و محدودیت‌های معاملاتی اعمال خواهد شد.</p>

                <div style="margin-top: 20px;">
                    <label for="selectedMarket" style="display: block; margin-bottom: 10px; font-weight: bold;">انتخاب بازار برای معاملات آتی:</label>
                    <select id="selectedMarket" class="form-control" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                        <option value="">انتخاب بازار...</option>
                        <option value="BTCUSDT">BTCUSDT (Bitcoin/USDT)</option>
                        <option value="ETHUSDT">ETHUSDT (Ethereum/USDT)</option>
                        <option value="ADAUSDT">ADAUSDT (Cardano/USDT)</option>
                        <option value="DOTUSDT">DOTUSDT (Polkadot/USDT)</option>
                        <option value="BNBUSDT">BNBUSDT (Binance Coin/USDT)</option>
                        <option value="XRPUSDT">XRPUSDT (Ripple/USDT)</option>
                        <option value="SOLUSDT">SOLUSDT (Solana/USDT)</option>
                        <option value="TRXUSDT">TRXUSDT (Tron/USDT)</option>
                        <option value="DOGEUSDT">DOGEUSDT (Dogecoin/USDT)</option>
                        <option value="LTCUSDT">LTCUSDT (Litecoin/USDT)</option>
                    </select>
                    <small style="color: #666; margin-top: 5px; display: block;">پس از انتخاب، تنها در این بازار می‌توانید معامله کنید</small>
                </div>
            </div>
            <div class="modal-buttons">
                <button type="button" class="btn btn-danger" onclick="activateFutureStrictMode()">
                    بله، فعال کن
                </button>
                <button type="button" class="btn btn-primary" onclick="closeModal()">
                    لغو
                </button>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        function showConfirmationModal() {
            document.getElementById('confirmationModal').style.display = 'block';
        }

        function closeModal() {
            document.getElementById('confirmationModal').style.display = 'none';
        }

        function activateFutureStrictMode() {
            // Validate market selection
            const selectedMarket = document.getElementById('selectedMarket').value;
            if (!selectedMarket) {
                showAlert('لطفاً ابتدا بازار مورد نعر را انتخاب کنید', 'danger');
                return;
            }

            // Show loading state
            const button = document.querySelector('.modal-buttons .btn-danger');
            const originalText = button.textContent;
            button.textContent = 'در حال پردازش...';
            button.disabled = true;

            fetch('{{ route("settings.activate-future-strict-mode") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    selected_market: selectedMarket
                })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Show success message and reload page
                        showAlert(data.message, 'success');
                        setTimeout(() => {
                            window.location.reload();
                        }, 2000);
                    } else {
                        // Show error message
                        showAlert(data.message, 'danger');
                        button.textContent = originalText;
                        button.disabled = false;
                    }
                    closeModal();
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAlert('خطا در برقراری ارتباط با سرور', 'danger');
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
