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
                                   max="{{ (isset($user) && $user->email === 'hesssan3506@gmail.com' && $user->future_strict_mode) ? '10' : '80' }}"
                                   step="0.01"
                                   placeholder="مثال: 2.5">
                            <span class="input-suffix">%</span>
                        </div>
                        @if(isset($user) && $user->email === 'hesssan3506@gmail.com' && $user->future_strict_mode)
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
        @if(isset($user) && $user->email === 'hesssan3506@gmail.com')
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

                    <!-- Strict Mode Profit/Loss Targets Form -->
                    <div class="setting-item" style="margin-top: 20px; border-top: 1px solid #444; padding-top: 20px;">
                        <h3 class="setting-title">اهداف سود و ضرر هفتگی و ماهانه</h3>
                        <div class="setting-description">
                            در این بخش می‌توانید اهداف هفتگی و ماهانه برای سود و ضرر خود تعیین کنید.
                            <br>
                            <strong style="color: #ffc107;">توجه مهم:</strong> پس از تنظیم، این اهداف غیرقابل تغییر و غیرقابل حذف هستند و برای همیشه ثبت می‌شوند.
                        </div>

                        <style>
                            /* Toggle Switch */
                            .switch {
                                position: relative;
                                display: inline-block;
                                width: 46px;
                                height: 24px;
                                vertical-align: middle;
                            }
                            .switch input {
                                opacity: 0;
                                width: 0;
                                height: 0;
                            }
                            .switch-slider {
                                position: absolute;
                                cursor: pointer;
                                top: 0;
                                left: 0;
                                right: 0;
                                bottom: 0;
                                background-color: #444;
                                transition: .3s;
                                border-radius: 24px;
                            }
                            .switch-slider:before {
                                position: absolute;
                                content: "";
                                height: 18px;
                                width: 18px;
                                left: 3px;
                                bottom: 3px;
                                background-color: white;
                                transition: .3s;
                                border-radius: 50%;
                            }
                            .switch input:checked + .switch-slider {
                                background-color: var(--primary-color, #ffc107);
                            }
                            .switch input:checked + .switch-slider:before {
                                transform: translateX(22px);
                            }
                            .switch input:disabled + .switch-slider {
                                opacity: 0.5;
                                cursor: not-allowed;
                            }

                            /* Slider CSS */
                            .dual-range-slider {
                                position: relative;
                                width: 100%;
                                height: 80px; /* Increased height */
                                margin-top: 15px;
                                margin-bottom: 20px;
                                padding: 0 15px; /* Side padding for thumbs */
                                box-sizing: border-box;
                            }
                            .slider-wrapper {
                                max-height: 0;
                                overflow: hidden;
                                opacity: 0;
                                transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
                                transform: translateY(-10px);
                            }
                            .slider-wrapper.expanded {
                                max-height: 120px;
                                opacity: 1;
                                transform: translateY(0);
                                margin-top: 15px;
                                margin-bottom: 20px;
                                overflow: visible; /* Allow thumbs to extend */
                            }
                            .slider-track-container {
                                position: absolute;
                                top: 35px;
                                left: 15px;
                                right: 15px;
                                height: 8px;
                                background: #333;
                                border-radius: 4px;
                                z-index: 1;
                            }
                            /* Zero Zone Marker */
                            .zero-zone-marker {
                                position: absolute;
                                top: 0;
                                bottom: 0;
                                width: 10%; /* 10% width for zero zone */
                                left: 45%; /* Starts at 45% */
                                background: #1e1e1e; /* Match page background */
                                z-index: 5; /* On top of bar */
                                border-left: 1px solid #333;
                                border-right: 1px solid #333;
                            }
                            .zero-zone-label {
                                position: absolute;
                                top: -25px;
                                left: 50%;
                                transform: translateX(-50%);
                                color: #666;
                                font-size: 12px;
                                font-weight: bold;
                            }
                            .slider-range-bar {
                                position: absolute;
                                top: 0;
                                bottom: 0;
                                background: var(--primary-color, #ffc107);
                                z-index: 3;
                                opacity: 0.8;
                                border-radius: 4px;
                            }
                            .slider-thumb {
                                position: absolute;
                                top: 26px; /* Centered relative to track top:35, height:8 -> center:39. Thumb height:26 -> top: 39-13=26 */
                                width: 26px;
                                height: 26px;
                                background: #fff;
                                border: 2px solid var(--primary-color, #ffc107);
                                border-radius: 6px; /* Square with rounded corners */
                                cursor: pointer;
                                z-index: 10; /* Highest */
                                box-shadow: 0 2px 6px rgba(0,0,0,0.4);
                                transition: transform 0.1s, box-shadow 0.1s;
                                margin-left: -13px; /* Center the thumb on the point */
                            }
                            .slider-thumb:hover {
                                transform: scale(1.1);
                                box-shadow: 0 4px 8px rgba(0,0,0,0.5);
                            }
                            .slider-thumb.dragging {
                                transform: scale(1.2);
                                cursor: grabbing;
                                z-index: 11;
                            }
                            .slider-value {
                                position: absolute;
                                top: -32px;
                                left: 50%;
                                transform: translateX(-50%);
                                background: #444;
                                color: #fff;
                                padding: 4px 8px;
                                border-radius: 6px;
                                font-size: 13px;
                                font-weight: bold;
                                white-space: nowrap;
                                box-shadow: 0 2px 4px rgba(0,0,0,0.2);
                            }
                            .slider-value:after {
                                content: '';
                                position: absolute;
                                bottom: -4px;
                                left: 50%;
                                transform: translateX(-50%);
                                border-width: 4px 4px 0;
                                border-style: solid;
                                border-color: #444 transparent transparent transparent;
                            }
                            .slider-labels {
                                position: absolute;
                                top: 55px;
                                width: 100%;
                                padding: 0 15px;
                                display: flex;
                                justify-content: space-between;
                                color: #888;
                                font-size: 12px;
                                pointer-events: none;
                            }
                            
                            /* Header with Switch */
                            .target-header {
                                display: flex;
                                align-items: center;
                                justify-content: space-between;
                                margin-bottom: 10px;
                                background: rgba(255,255,255,0.05);
                                padding: 10px 15px;
                                border-radius: 8px;
                            }
                            .target-title {
                                font-weight: 600;
                                font-size: 1.1em;
                                color: #eee;
                                display: flex;
                                align-items: center;
                                gap: 10px;
                            }

                            /* Saved Range Display */
                            .saved-range-display {
                                background: rgba(40, 167, 69, 0.15);
                                border: 1px solid #28a745;
                                border-radius: 12px;
                                padding: 15px;
                                margin-top: 10px;
                                color: #fff;
                                display: flex;
                                align-items: center;
                                justify-content: space-between;
                                flex-wrap: wrap;
                                gap: 15px;
                            }
                            .saved-info {
                                display: flex;
                                align-items: center;
                                gap: 10px;
                            }
                            .saved-values {
                                display: flex;
                                gap: 15px;
                            }
                            .saved-value-box {
                                background: rgba(0,0,0,0.2);
                                padding: 5px 12px;
                                border-radius: 6px;
                                text-align: center;
                            }
                            .saved-value-box span {
                                display: block;
                                font-size: 0.8em;
                                color: #aaa;
                            }
                            .saved-value-box strong {
                                color: #ffc107;
                                font-size: 1.1em;
                            }


                            #targetsConfirmationModal .modal-content {
                                background: #2a2a2a;
                                border: 1px solid #444;
                                border-radius: 12px;
                                width: 90%;
                                max-width: 500px;
                                box-shadow: 0 10px 30px rgba(0,0,0,0.5);
                                animation: modalFadeIn 0.3s ease;
                                margin: auto; /* Override default margin */
                                margin-top:200px;
                            }
                            @keyframes modalFadeIn {
                                from { opacity: 0; transform: translateY(-20px); }
                                to { opacity: 1; transform: translateY(0); }
                            }
                        </style>

                        <form action="{{ route('account-settings.update-strict-limits') }}" method="POST" id="strictTargetsForm">
                            @csrf

                            @if($errors->any())
                                <div class="alert alert-danger" style="border-radius: 8px; margin-bottom: 20px;">
                                    <ul style="margin: 0; padding-right: 20px;">
                                        @foreach($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                            
                            <!-- Weekly Targets -->
                            <div class="form-group">
                                <div class="target-header">
                                    <div class="target-title">
                                        <i class="fas fa-calendar-week"></i> اهداف هفتگی
                                    </div>
                                    <div style="font-size: 0.85em; color: #aaa; margin-top: 5px; width: 100%;">
                                        با رسیدن به هر یک از اهداف، معاملات تا پایان هفته قفل خواهد شد.
                                    </div>
                                    @if($weeklyProfitLimit === null || $weeklyLossLimit === null)
                                        <div class="switch-wrapper">
                                            <label class="switch">
                                                <input type="checkbox" id="weekly_limit_enabled" name="weekly_limit_enabled" value="1" 
                                                    {{ $weeklyLimitEnabled ? 'checked' : '' }}>
                                                <span class="switch-slider"></span>
                                            </label>
                                        </div>
                                    @endif
                                </div>

                                @if($weeklyProfitLimit !== null && $weeklyLossLimit !== null)
                                    <div class="saved-range-display">
                                        <div class="saved-info">
                                            <i class="fas fa-lock" style="color: #28a745; font-size: 1.2em;"></i>
                                            <div>
                                                <div style="font-weight: bold; color: #28a745;">ثبت شده</div>
                                                <div style="font-size: 0.85em; opacity: 0.8;">غیرقابل تغییر</div>
                                            </div>
                                        </div>
                                        <div class="saved-values">
                                            <div class="saved-value-box">
                                                <span>هدف سود</span>
                                                <strong>+{{ number_format($weeklyProfitLimit, 0) }}%</strong>
                                            </div>
                                            <div class="saved-value-box">
                                                <span>هدف ضرر</span>
                                                <strong>-{{ number_format($weeklyLossLimit, 0) }}%</strong>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- No hidden inputs for existing values needed, as they are immutable -->
                                @else
                                    <div class="slider-wrapper" id="weekly-slider-wrapper">
                                        <div class="dual-range-slider" id="weekly-slider">
                                            <div class="slider-track-container">
                                                <div class="zero-zone-marker">
                                                    <span class="zero-zone-label">0%</span>
                                                </div>
                                                <div class="slider-range-bar"></div>
                                            </div>
                                            
                                            <div class="slider-thumb left" data-type="max">
                                                <div class="slider-value">0%</div>
                                            </div>
                                            <div class="slider-thumb right" data-type="min">
                                                <div class="slider-value">0%</div>
                                            </div>

                                            <div class="slider-labels">
                                                <span>-30%</span>
                                                <span>+25%</span>
                                            </div>

                                            <input type="hidden" name="weekly_loss_limit" id="weekly_loss_input" value="">
                                            <input type="hidden" name="weekly_profit_limit" id="weekly_profit_input" value="">
                                        </div>
                                    </div>
                                @endif
                            </div>

                            <!-- Monthly Targets -->
                            <div class="form-group" style="margin-top: 25px;">
                                <div class="target-header">
                                    <div class="target-title">
                                        <i class="fas fa-calendar-alt"></i> اهداف ماهانه
                                    </div>
                                    <div style="font-size: 0.85em; color: #aaa; margin-top: 5px; width: 100%;">
                                        با رسیدن به هر یک از اهداف، معاملات تا پایان ماه قفل خواهد شد.
                                    </div>
                                    @if($monthlyProfitLimit === null || $monthlyLossLimit === null)
                                        <div class="switch-wrapper">
                                            <label class="switch">
                                                <input type="checkbox" id="monthly_limit_enabled" name="monthly_limit_enabled" value="1" 
                                                    {{ $monthlyLimitEnabled ? 'checked' : '' }}>
                                                <span class="switch-slider"></span>
                                            </label>
                                        </div>
                                    @endif
                                </div>

                                @if($monthlyProfitLimit !== null && $monthlyLossLimit !== null)
                                    <div class="saved-range-display">
                                        <div class="saved-info">
                                            <i class="fas fa-lock" style="color: #28a745; font-size: 1.2em;"></i>
                                            <div>
                                                <div style="font-weight: bold; color: #28a745;">ثبت شده</div>
                                                <div style="font-size: 0.85em; opacity: 0.8;">غیرقابل تغییر</div>
                                            </div>
                                        </div>
                                        <div class="saved-values">
                                            <div class="saved-value-box">
                                                <span>هدف سود</span>
                                                <strong>+{{ number_format($monthlyProfitLimit, 0) }}%</strong>
                                            </div>
                                            <div class="saved-value-box">
                                                <span>هدف ضرر</span>
                                                <strong>-{{ number_format($monthlyLossLimit, 0) }}%</strong>
                                            </div>
                                        </div>
                                    </div>
                                @else
                                    <div class="slider-wrapper" id="monthly-slider-wrapper">
                                        <div class="dual-range-slider" id="monthly-slider">
                                            <div class="slider-track-container">
                                                <div class="zero-zone-marker">
                                                    <span class="zero-zone-label">0%</span>
                                                </div>
                                                <div class="slider-range-bar"></div>
                                            </div>
                                            
                                            <div class="slider-thumb left" data-type="max">
                                                <div class="slider-value">0%</div>
                                            </div>
                                            <div class="slider-thumb right" data-type="min">
                                                <div class="slider-value">0%</div>
                                            </div>

                                            <div class="slider-labels">
                                                <span>-50%</span>
                                                <span>+60%</span>
                                            </div>

                                            <input type="hidden" name="monthly_loss_limit" id="monthly_loss_input" value="">
                                            <input type="hidden" name="monthly_profit_limit" id="monthly_profit_input" value="">
                                        </div>
                                    </div>
                                @endif
                            </div>

                            @if(($weeklyProfitLimit === null || $weeklyLossLimit === null) || ($monthlyProfitLimit === null || $monthlyLossLimit === null))
                                <div class="button-group" style="margin-top: 30px;">
                                    <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px; font-size: 1.1em;">
                                        <i class="fas fa-check-circle"></i> ثبت نهایی اهداف
                                    </button>
                                </div>
                            @endif
                        </form>

                        <!-- Targets Confirmation Modal -->


                        <script>
                            document.addEventListener('DOMContentLoaded', function() {
                                const form = document.getElementById('strictTargetsForm');

                                // Toggle Animation Logic
                                function setupToggle(toggleId, wrapperId) {
                                    const toggle = document.getElementById(toggleId);
                                    const wrapper = document.getElementById(wrapperId);
                                    if (!toggle || !wrapper) return;

                                    function updateState() {
                                        if (toggle.checked) {
                                            wrapper.classList.add('expanded');
                                        } else {
                                            wrapper.classList.remove('expanded');
                                        }
                                    }

                                    toggle.addEventListener('change', updateState);
                                    updateState(); // Initial state
                                }

                                setupToggle('weekly_limit_enabled', 'weekly-slider-wrapper');
                                setupToggle('monthly_limit_enabled', 'monthly-slider-wrapper');

                                // Slider Logic with Zero Zone (Reversed: Profit Left, Loss Right)
                                function initSlider(sliderId, minVal, maxVal, minLimit, maxLimit, inputMinId, inputMaxId) {
                                    const slider = document.getElementById(sliderId);
                                    if (!slider) return;
                                    
                                    const thumbLeft = slider.querySelector('.slider-thumb.left'); // Profit (Positive)
                                    const thumbRight = slider.querySelector('.slider-thumb.right'); // Loss (Negative)
                                    const rangeBar = slider.querySelector('.slider-range-bar');
                                    const inputMin = document.getElementById(inputMinId); // Loss Input
                                    const inputMax = document.getElementById(inputMaxId); // Profit Input
                                    
                                    // Configuration
                                    const ABS_MIN = minVal; // e.g. -30
                                    const ABS_MAX = maxVal; // e.g. 25
                                    
                                    // Visual Split: 45% Profit | 10% Zero | 45% Loss
                                    const ZERO_START = 45;
                                    const ZERO_END = 55;
                                    
                                    // Initial values
                                    let currentLoss = minLimit; // e.g. -1
                                    let currentProfit = maxLimit; // e.g. 1

                                    // Helper: Value to Percent
                                    function valueToPercent(val) {
                                        if (val > 0) {
                                            // Profit Zone (0% to 45%)
                                            // val=MAX -> 0%, val=1 -> 45%
                                            // Formula: (MAX - val) / (MAX - 1) * 45
                                            // Wait, if val=MAX -> 0%. If val=1 -> 45%.
                                            // Correct: Left side is MAX.
                                            return ((ABS_MAX - val) / (ABS_MAX - 1)) * 45;
                                        } else {
                                            // Loss Zone (55% to 100%)
                                            // val=-1 -> 55%, val=MIN -> 100%
                                            // Formula: 55 + ((-1 - val) / (-1 - ABS_MIN)) * 45
                                            return 55 + ((-1 - val) / (-1 - ABS_MIN)) * 45;
                                        }
                                    }

                                    // Helper: Percent to Value
                                    function percentToValue(pct) {
                                        if (pct <= 45) {
                                            // Profit Zone
                                            // pct = ((MAX - val) / (MAX - 1)) * 45
                                            // val = MAX - (pct / 45) * (MAX - 1)
                                            let val = ABS_MAX - (pct / 45) * (ABS_MAX - 1);
                                            return Math.max(1, Math.min(ABS_MAX, Math.round(val)));
                                        } else if (pct >= 55) {
                                            // Loss Zone
                                            // pct = 55 + ((-1 - val) / (-1 - MIN)) * 45
                                            // (pct - 55) / 45 = (-1 - val) / (-1 - MIN)
                                            // val = -1 - ((pct - 55) / 45) * (-1 - ABS_MIN)
                                            let val = -1 - ((pct - 55) / 45) * (-1 - ABS_MIN);
                                            return Math.min(-1, Math.max(ABS_MIN, Math.round(val)));
                                        }
                                        return 0; // Should not happen due to dead zone logic
                                    }

                                    function updateUI() {
                                        const leftPos = valueToPercent(currentProfit);
                                        const rightPos = valueToPercent(currentLoss);

                                        // Update Thumbs
                                        // Use leftPos for left thumb, rightPos for right thumb
                                        thumbLeft.style.left = `${leftPos}%`;
                                        thumbRight.style.left = `${rightPos}%`;
                                        
                                        // Update Range Bar
                                        // From Left Thumb to Right Thumb
                                        rangeBar.style.left = `${leftPos}%`;
                                        rangeBar.style.width = `${rightPos - leftPos}%`;

                                        // Update Labels
                                        thumbLeft.querySelector('.slider-value').textContent = '+' + currentProfit + '%';
                                        thumbRight.querySelector('.slider-value').textContent = '-' + Math.abs(currentLoss) + '%';

                                        // Update Inputs
                                        inputMin.value = Math.abs(currentLoss);
                                        inputMax.value = currentProfit;
                                    }

                                    function handleDrag(thumb, isProfitThumb) {
                                        let isDragging = false;

                                        thumb.addEventListener('mousedown', startDrag);
                                        thumb.addEventListener('touchstart', startDrag, {passive: false});

                                        function startDrag(e) {
                                            isDragging = true;
                                            thumb.classList.add('dragging');
                                            document.addEventListener('mousemove', onDrag);
                                            document.addEventListener('touchmove', onDrag, {passive: false});
                                            document.addEventListener('mouseup', stopDrag);
                                            document.addEventListener('touchend', stopDrag);
                                            e.preventDefault();
                                        }

                                        function onDrag(e) {
                                            if (!isDragging) return;
                                            
                                            const rect = slider.querySelector('.slider-track-container').getBoundingClientRect();
                                            const clientX = e.touches ? e.touches[0].clientX : e.clientX;
                                            
                                            // Calculate percentage from LEFT of track
                                            let percent = (clientX - rect.left) / rect.width * 100;
                                            percent = Math.max(0, Math.min(100, percent));
                                            
                                            // Dead Zone Logic
                                            if (percent > 45 && percent < 55) {
                                                // Snap to closest edge
                                                if (percent < 50) percent = 45;
                                                else percent = 55;
                                            }

                                            // Determine Value
                                            let val = percentToValue(percent);

                                            if (isProfitThumb) {
                                                // Profit Thumb (Left side, Positive)
                                                // Must stay in Profit Zone (<= 45%)
                                                if (percent > 45) val = 1;
                                                currentProfit = val;
                                            } else {
                                                // Loss Thumb (Right side, Negative)
                                                // Must stay in Loss Zone (>= 55%)
                                                if (percent < 55) val = -1;
                                                currentLoss = val;
                                            }

                                            updateUI();
                                        }

                                        function stopDrag() {
                                            isDragging = false;
                                            thumb.classList.remove('dragging');
                                            document.removeEventListener('mousemove', onDrag);
                                            document.removeEventListener('touchmove', onDrag);
                                            document.removeEventListener('mouseup', stopDrag);
                                            document.removeEventListener('touchend', stopDrag);
                                        }
                                    }

                                    handleDrag(thumbLeft, true);  // Left thumb controls Profit
                                    handleDrag(thumbRight, false); // Right thumb controls Loss
                                    
                                    updateUI();
                                }

                                // Initialize Sliders
                                // Weekly: -30 to +25
                                initSlider('weekly-slider', -30, 25, -1, 1, 'weekly_loss_input', 'weekly_profit_input');
                                // Monthly: -50 to +60
                                initSlider('monthly-slider', -50, 60, -3, 3, 'monthly_loss_input', 'monthly_profit_input');
                                
                                // Save Button State Logic
                                const weeklyToggle = document.getElementById('weekly_limit_enabled');
                                const monthlyToggle = document.getElementById('monthly_limit_enabled');
                                const submitBtn = form ? form.querySelector('button[type="submit"]') : null;

                                function updateSubmitButtonState() {
                                    if (!submitBtn) return;
                                    
                                    const isWeeklyChecked = weeklyToggle ? weeklyToggle.checked : false;
                                    const isMonthlyChecked = monthlyToggle ? monthlyToggle.checked : false;

                                    if (!isWeeklyChecked && !isMonthlyChecked) {
                                        submitBtn.disabled = true;
                                        submitBtn.style.opacity = '0.5';
                                        submitBtn.style.cursor = 'not-allowed';
                                    } else {
                                        submitBtn.disabled = false;
                                        submitBtn.style.opacity = '1';
                                        submitBtn.style.cursor = 'pointer';
                                    }
                                }

                                if (weeklyToggle) weeklyToggle.addEventListener('change', updateSubmitButtonState);
                                if (monthlyToggle) monthlyToggle.addEventListener('change', updateSubmitButtonState);
                                updateSubmitButtonState(); // Initial check

                                // Form Submission Interception
                                if (form) {
                                    form.addEventListener('submit', function(e) {
                                        e.preventDefault();
                                        showTargetsConfirmationModal();
                                    });
                                }
                            });

                            // Modal Functions
                            function showTargetsConfirmationModal() {
                                const modal = document.getElementById('targetsConfirmationModal');
                                modal.style.display = 'block'; // Use block to match other modals
                            }

                            function closeTargetsModal() {
                                document.getElementById('targetsConfirmationModal').style.display = 'none';
                            }

                            function submitTargetsForm() {
                                document.getElementById('strictTargetsForm').submit();
                            }
                        </script>
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
                        <option value="3:1" selected>3:1 (ضرر سه برابر سود)</option>
                        <option value="2:1">2:1 (ضرر دو برابر سود)</option>
                        <option value="1:1">1:1 (ضرر برابر سود)</option>
                        <option value="1:2">1:2 (ضرر نصف سود)</option>
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
    <!-- Targets Confirmation Modal -->
    <div id="targetsConfirmationModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>تأیید اهداف سود و ضرر</h3>
                <span class="close" onclick="closeTargetsModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning">
                    <strong>هشدار:</strong> این اهداف پس از ثبت غیرقابل تغییر هستند!
                </div>
                <p>آیا از مقادیر تعیین شده برای اهداف هفتگی و ماهانه اطمینان دارید؟</p>
                <ul style="margin-top: 10px;">
                    <li>این مقادیر برای همیشه ثبت می‌شوند.</li>
                    <li>پس از ثبت، امکان ویرایش یا حذف وجود ندارد.</li>
                </ul>
            </div>
            <div class="modal-footer">
                <div class="modal-buttons">
                    <button type="button" class="btn btn-secondary" onclick="closeTargetsModal()">بازگشت و اصلاح</button>
                    <button type="button" class="btn btn-success" onclick="submitTargetsForm()">تأیید و ثبت نهایی</button>
                </div>
            </div>
        </div>
    </div>
    @endif
@endsection

@push('scripts')
    <script>
        // Update max risk value when strict mode changes
        document.addEventListener('DOMContentLoaded', function() {
            const riskInput = document.getElementById('default_risk');
            const strictMode = {{ (isset($user) && $user->email === 'hesssan3506@gmail.com' && $user->future_strict_mode) ? 'true' : 'false' }};

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
