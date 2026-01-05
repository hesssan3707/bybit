@extends('layouts.app')

@section('title', 'تنظیمات حساب کاربری')

@push('styles')
    <style>
        .account-settings {
            direction: rtl;
        }

        .account-settings__container {
            width: 100%;
            padding: 18px;
            box-sizing: border-box;
            position: relative;
            z-index: 2;
        }

        .account-settings__layout {
            display: flex;
            gap: 16px;
            align-items: flex-start;
            margin-top: 55px;
            flex-direction: row;
            width: 100%;
            max-width: 1200px;
            margin-right: auto;
            margin-left: auto;
            position: relative;
        }

        .account-settings__title {
            margin: 0 0 10px;
            text-align: center;
            font-size: 20px;
            font-weight: 900;
        }

        .account-settings__meta {
            margin-top: 0;
            display: flex;
            justify-content: flex-start;
        }

        .account-settings__back-link {
            display: none;
        }

        .account-settings__tabs {
            width: 230px;
            flex: 0 0 230px;
            display: flex;
            flex-direction: column;
            gap: 4px;
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 8px;
            height: fit-content;
            z-index: 1;
        }

        .account-settings__tab {
            width: 100%;
            text-align: right;
            padding: 12px 16px;
            background: transparent;
            border: 0;
            color: rgba(255, 255, 255, 0.7);
            cursor: pointer;
            font-size: 14px;
            font-weight: 700;
            border-radius: 14px;
            transition: all .2s ease;
            white-space: nowrap;
        }

        .account-settings__tab:hover {
            background: rgba(255, 255, 255, 0.08);
            color: #ffffff;
        }

        .account-settings__tab[aria-selected="true"] {
            color: #ffffff;
            background: var(--primary-color, #007bff);
            box-shadow: 0 4px 15px rgba(0, 123, 255, 0.3);
        }

        .account-settings__tab:last-child {
            border-bottom: 0;
        }

        .account-settings__tab:hover {
            color: rgba(255,255,255,0.98);
            background: rgba(255,255,255,0.06);
        }

        .account-settings__tab[aria-selected="true"] {
            color: #ffffff;
            background: var(--primary-color, #007bff);
            box-shadow: 0 10px 24px rgba(0,0,0,0.22);
        }

        .account-settings__panels {
            flex: 1 1 auto;
            min-width: 0;
        }

        .account-settings__panel {
            display: none;
        }

        .account-settings__panel.is-active {
            display: block;
        }

        .settings-card {
            padding-top: 4px;
        }

        .settings-title {
            margin: 0 0 16px;
            text-align: right;
            color: rgba(255,255,255,0.95);
            font-size: 18px;
            font-weight: 900;
        }

        .settings-subtitle {
            margin: 0 0 12px;
            color: rgba(255,255,255,0.92);
            font-size: 15px;
            font-weight: 900;
        }

        .setting-description {
            color: rgba(255,255,255,0.85);
            font-size: 14px;
            line-height: 1.7;
            margin-bottom: 12px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }

        .form-group {
            margin-bottom: 12px;
            text-align: right;
        }

        .form-group label {
            display: block;
            margin-bottom: 7px;
            color: rgba(255,255,255,0.9);
            font-weight: 900;
            font-size: 13px;
        }

        .form-control {
            width: 100%;
            padding: 11px 12px;
            border: 1px solid var(--glass-border, rgba(255, 255, 255, 0.18));
            background: rgba(255, 255, 255, 0.10);
            color: #e5e7eb;
            box-sizing: border-box;
            border-radius: 10px;
            font-size: 14px;
        }

        .form-control:focus {
            outline: none;
            border-color: rgba(59,130,246,0.65);
            box-shadow: 0 0 0 2px rgba(59,130,246,0.20);
        }

        select.form-control option,
        select.form-control optgroup {
            background-color: #ffffff;
            color: #111827;
        }

        .input-group {
            display: flex;
            align-items: center;
            border: 1px solid var(--glass-border, rgba(255, 255, 255, 0.18));
            background: rgba(255, 255, 255, 0.10);
            border-radius: 10px;
        }

        .input-group .form-control {
            border: 0;
            background: transparent;
            border-radius: 10px;
        }

        .input-suffix {
            padding: 0 10px;
            color: rgba(255,255,255,0.7);
            font-weight: 900;
        }

        .button-row {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: flex-start;
            margin-top: 14px;
        }

        .warning-box {
            border: 1px solid rgba(255,193,7,0.45);
            background: rgba(255,193,7,0.12);
            color: rgba(255,240,200,0.95);
            padding: 12px 14px;
            border-radius: 12px;
            margin-bottom: 12px;
        }

        .muted {
            color: rgba(255,255,255,0.7);
            font-size: 12px;
            line-height: 1.6;
        }

        .split-card {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }

        .card-section {
            border: 1px solid rgba(255,255,255,0.14);
            padding: 14px;
            border-radius: 12px;
        }

        .progress-row {
            margin-top: 10px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .progress-box {
            border: 1px solid rgba(255,255,255,0.14);
            padding: 12px;
            border-radius: 12px;
        }

        .progress-title {
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: rgba(255,255,255,0.9);
            font-weight: 900;
            font-size: 13px;
            margin-bottom: 8px;
        }

        .progress-bar {
            height: 10px;
            background: rgba(255,255,255,0.12);
            overflow: hidden;
            border-radius: 999px;
            position: relative;
        }

        .progress-fill {
            height: 100%;
            width: 0;
            background: linear-gradient(90deg, #28a745, #20c997);
            position: absolute;
            left: 0;
            top: 0;
        }

        .progress-fill.loss {
            background: linear-gradient(90deg, #ff6b6b, #dc3545);
            left: auto;
            right: 0;
        }

        .account-settings__footer {
            margin-top: 18px;
            display: flex;
            justify-content: flex-start;
        }

        .modal {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.6);
            z-index: 2000;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 16px;
        }

        .modal.is-open {
            display: flex;
        }

        .modal__dialog {
            width: 100%;
            max-width: 520px;
            background: rgba(10,10,10,0.92);
            border: 1px solid rgba(255,255,255,0.18);
            padding: 16px;
            border-radius: 16px;
        }

        .modal__header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 12px;
        }

        .modal__title {
            margin: 0;
            color: rgba(255,255,255,0.95);
            font-size: 16px;
            font-weight: 900;
        }

        .modal__close {
            width: 40px;
            height: 40px;
            border-radius: 10px;
        }

        @media (max-width: 900px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            .split-card {
                grid-template-columns: 1fr;
            }
            .progress-row {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 820px) {
            .account-settings__layout {
                flex-direction: column;
                gap: 12px;
            }
            .account-settings__container {
                margin-right: 0;
            }
            .account-settings__tabs {
                width: 100%;
                flex: 0 0 auto;
                display: flex;
                flex-direction: row;
                gap: 8px;
                background: rgba(255, 255, 255, 0.05);
                backdrop-filter: blur(10px);
                border: 1px solid rgba(255, 255, 255, 0.1);
                border-radius: 16px;
                padding: 6px;
                transform: none;
                overflow-x: auto;
                margin-left: 0;
            }
            .account-settings__panels {
                width: 100%;
            }
            .account-settings__tab {
                width: auto;
                text-align: center;
                white-space: nowrap;
                flex: 0 0 auto;
                min-width: 120px;
                padding: 10px 14px;
                border: 0;
                border-radius: 12px;
                transform: none !important;
                transition: all .2s ease;
            }
            .account-settings__tab:last-child {
                border-bottom: 0;
            }
            .button-row .btn-glass {
                flex: 1 1 auto;
                min-width: 140px;
            }
            .form-control {
                font-size: 16px;
            }
        }

        @media (max-width: 420px) {
            .account-settings__tab {
                padding: 10px 12px;
                font-size: 13px;
            }
        }
    </style>
@endpush

@section('content')
    @php
        $isStrictAdmin = isset($user) && $user->email === 'hesssan3506@gmail.com';
        $strictModeActive = (bool)($user->future_strict_mode ?? false);
        $selfBanTimeRemainingMinutes = (isset($selfBanTime) && $selfBanTime) ? max(0, $selfBanTime->ends_at->diffInMinutes(now())) : null;
        $selfBanPriceRemainingMinutes = (isset($selfBanPrice) && $selfBanPrice) ? max(0, $selfBanPrice->ends_at->diffInMinutes(now())) : null;
    @endphp

    <div class="account-settings" id="accountSettingsRoot">
        <div class="account-settings__layout">
            <nav class="account-settings__tabs" role="tablist" aria-label="تنظیمات حساب کاربری">
                <button type="button" class="account-settings__tab" data-tab="general" role="tab" aria-selected="true">عمومی</button>
                @if($isStrictAdmin)
                    <button type="button" class="account-settings__tab" data-tab="strict" role="tab" aria-selected="false">حالت سخت‌گیرانه و اهداف</button>
                    <button type="button" class="account-settings__tab" data-tab="blocking" role="tab" aria-selected="false">مسدودسازی (زمان/قیمت)</button>
                @endif
            </nav>

            <div class="container glass-card account-settings__container">
                <h2 class="account-settings__title">تنظیمات حساب کاربری</h2>
                <div class="account-settings__meta">
                </div>
            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
            @endif

            @if($errors->any())
                <div class="alert alert-danger">
                    <ul style="margin:0; padding-right:18px;">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

                <div class="account-settings__panels">
                    <section class="account-settings__panel is-active" data-panel="general" role="tabpanel">
                        <div class="settings-card">
                            <h3 class="settings-title">عمومی</h3>

                            <form action="{{ route('account-settings.update') }}" method="POST">
                                @csrf

                                <div class="form-grid">
                                    <div class="form-group">
                                        <label for="default_risk">ریسک پیش‌فرض (درصد)</label>
                                        <div class="input-group">
                                            <input
                                                type="number"
                                                id="default_risk"
                                                name="default_risk"
                                                class="form-control"
                                                value="{{ $defaultRisk }}"
                                                min="1"
                                                max="{{ $strictModeActive ? '10' : '80' }}"
                                                step="0.01"
                                                inputmode="decimal"
                                                placeholder="مثال: 2.5"
                                            >
                                            <span class="input-suffix">%</span>
                                        </div>
                                        @if($strictModeActive)
                                            <div class="muted">در حالت سخت‌گیرانه حداکثر 10 درصد مجاز است</div>
                                        @endif
                                    </div>

                                    <div class="form-group">
                                        <label for="default_expiration_time">زمان انقضای پیش‌فرض (دقیقه)</label>
                                        <div class="input-group">
                                            <input
                                                type="number"
                                                id="default_expiration_time"
                                                name="default_expiration_time"
                                                class="form-control"
                                                value="{{ $defaultExpirationTime }}"
                                                min="1"
                                                max="1000"
                                                inputmode="numeric"
                                                placeholder="خالی بگذارید برای عدم تنظیم"
                                            >
                                            <span class="input-suffix">دقیقه</span>
                                        </div>
                                        <div class="muted">حداکثر 999 دقیقه</div>
                                    </div>

                                    <div class="form-group">
                                        <label for="default_future_order_steps">تعداد پله پیش‌فرض (ثبت معامله آتی)</label>
                                        <div class="input-group">
                                            <input
                                                type="number"
                                                id="default_future_order_steps"
                                                name="default_future_order_steps"
                                                class="form-control"
                                                value="{{ $defaultFutureOrderSteps }}"
                                                min="1"
                                                max="8"
                                                inputmode="numeric"
                                                placeholder="مثال: 2"
                                            >
                                            <span class="input-suffix">عدد</span>
                                        </div>
                                        <div class="muted">حداکثر 8 پله</div>
                                    </div>

                                    <div class="form-group">
                                        <label for="tv_default_interval">بازه زمانی پیش‌فرض نمودار (TradingView)</label>
                                        <select id="tv_default_interval" name="tv_default_interval" class="form-control">
                                            <option value="1" {{ (isset($tvDefaultInterval) && $tvDefaultInterval === '1') ? 'selected' : '' }}>۱ دقیقه</option>
                                            <option value="5" {{ (isset($tvDefaultInterval) && $tvDefaultInterval === '5') ? 'selected' : '' }}>۵ دقیقه</option>
                                            <option value="15" {{ (isset($tvDefaultInterval) && $tvDefaultInterval === '15') ? 'selected' : '' }}>۱۵ دقیقه</option>
                                            <option value="60" {{ (isset($tvDefaultInterval) && $tvDefaultInterval === '60') ? 'selected' : '' }}>۱ ساعت</option>
                                            <option value="240" {{ (isset($tvDefaultInterval) && $tvDefaultInterval === '240') ? 'selected' : '' }}>۴ ساعت</option>
                                            <option value="D" {{ (isset($tvDefaultInterval) && $tvDefaultInterval === 'D') ? 'selected' : '' }}>۱ روز</option>
                                        </select>
                                        <div class="muted">این تنظیم فقط نمایش نمودار را تغییر می‌دهد</div>
                                    </div>
                                </div>

                                <div class="button-row">
                                    <button type="submit" class="btn-glass btn-glass-primary">ذخیره تنظیمات</button>
                                </div>
                            </form>
                        </div>
                    </section>

                    @if($isStrictAdmin)
                        <section class="account-settings__panel" data-panel="strict" role="tabpanel">
                            <div class="settings-card">
                                <h3 class="settings-title">حالت سخت‌گیرانه و اهداف</h3>

                                <div id="alert-container"></div>

                                <div class="setting-description">
                                    این حالت زمانی که فعال شود، موارد زیر را تحت تأثیر قرار می‌دهد:
                                    <ul style="margin:10px 0 0; padding-right:18px;">
                                        <li>امکان حذف یا تغییر Stop Loss و Take Profit وجود ندارد</li>
                                        <li>تنها از طریق این سیستم می‌توانید سفارش جدید ثبت کنید</li>
                                        <li>در صورت بستن سفارش فعال خارج از نقاط حدضرر/حدسود، تا 3 روز امکان ثبت سفارش ندارید</li>
                                        <li>پس از بستن سفارش فعال، تا یک هفته امکان بستن مجدد دستی ندارید</li>
                                        <li>حداکثر ریسک هر پوزیشن 10 درصد است</li>
                                        <li>پس از ضرر، باید 1 ساعت صبر کنید</li>
                                        <li>اگر دو بار پشت سر هم ضرر کنید، تا 24 ساعت امکان ثبت سفارش ندارید</li>
                                        <li>تنها در بازار انتخابی قابلیت معامله دارید</li>
                                        <li>این حالت پس از فعال‌سازی غیرقابل غیرفعال‌سازی است</li>
                                    </ul>
                                </div>

                                @if($strictModeActive)
                                    <div class="alert alert-success">
                                        <div style="font-weight:900;">حالت سخت‌گیرانه آتی فعال است</div>
                                        @if(isset($user->future_strict_mode_activated_at) && $user->future_strict_mode_activated_at)
                                            <div class="muted" style="margin-top:6px;">تاریخ فعال‌سازی: {{ $user->future_strict_mode_activated_at->format('Y/m/d H:i') }}</div>
                                        @endif
                                        @if(isset($user->selected_market) && $user->selected_market)
                                            <div class="muted" style="margin-top:6px;">بازار انتخابی: {{ $user->selected_market }}</div>
                                        @endif
                                        @if(isset($minRrRatio) && $minRrRatio)
                                            <div class="muted" style="margin-top:6px;">حداقل نسبت سود به ضرر: {{ $minRrRatio }}</div>
                                        @endif
                                    </div>

                                    <div class="split-card">
                                        <div class="card-section">
                                            <h4 class="settings-subtitle">اهداف هفتگی</h4>
                                            @if($weeklyProfitLimit !== null && $weeklyLossLimit !== null)
                                                @php
                                                    $weeklyPnl = isset($weeklyPnlPercent) ? (float)$weeklyPnlPercent : 0.0;
                                                    $weeklyProfitFill = ($weeklyPnl > 0 && $weeklyProfitLimit > 0) ? max(0, min(100, ($weeklyPnl / $weeklyProfitLimit) * 100)) : 0.0;
                                                    $weeklyLossFill = ($weeklyPnl < 0 && $weeklyLossLimit > 0) ? max(0, min(100, (abs($weeklyPnl) / $weeklyLossLimit) * 100)) : 0.0;
                                                @endphp
                                                <div class="muted">ثبت شده و غیرقابل تغییر</div>
                                                <div class="progress-row">
                                                    <div class="progress-box">
                                                        <div class="progress-title"><span>هدف سود</span><span>+{{ number_format($weeklyProfitLimit, 0) }}%</span></div>
                                                        <div class="progress-bar"><div class="progress-fill" style="width: {{ $weeklyProfitFill }}%;"></div></div>
                                                        <div class="muted" style="margin-top:6px;">سود فعلی: {{ number_format(max(0, $weeklyPnl), 1) }}%</div>
                                                    </div>
                                                    <div class="progress-box">
                                                        <div class="progress-title"><span>حد ضرر</span><span>-{{ number_format($weeklyLossLimit, 0) }}%</span></div>
                                                        <div class="progress-bar"><div class="progress-fill loss" style="width: {{ $weeklyLossFill }}%;"></div></div>
                                                        <div class="muted" style="margin-top:6px;">ضرر فعلی: -{{ number_format(max(0, abs(min(0, $weeklyPnl))), 1) }}%</div>
                                                    </div>
                                                </div>
                                                <div class="muted" style="margin-top:8px;">با رسیدن به هر یک از اهداف، معاملات تا پایان هفته قفل می‌شود.</div>
                                            @else
                                                <div class="warning-box">
                                                    با رسیدن به هر یک از اهداف، معاملات تا پایان هفته قفل می‌شود. این تنظیم پس از ثبت غیرقابل تغییر است.
                                                </div>
                                                <div class="form-group">
                                                    <label>
                                                        <input type="checkbox" id="weekly_limit_enabled" name="weekly_limit_enabled" value="1" form="strictTargetsForm" {{ $weeklyLimitEnabled ? 'checked' : '' }}>
                                                        فعال‌سازی محدودیت هفتگی
                                                    </label>
                                                </div>
                                                <div class="form-grid" data-dependent="weekly">
                                                    <div class="form-group">
                                                        <label for="weekly_profit_limit">هدف سود هفتگی (درصد)</label>
                                                        <input type="number" id="weekly_profit_limit" name="weekly_profit_limit" class="form-control" form="strictTargetsForm" min="1" max="25" step="0.1" inputmode="decimal" placeholder="مثال: 10">
                                                    </div>
                                                    <div class="form-group">
                                                        <label for="weekly_loss_limit">حد ضرر هفتگی (درصد)</label>
                                                        <input type="number" id="weekly_loss_limit" name="weekly_loss_limit" class="form-control" form="strictTargetsForm" min="1" max="30" step="0.1" inputmode="decimal" placeholder="مثال: 8">
                                                    </div>
                                                </div>
                                            @endif
                                        </div>

                                        <div class="card-section">
                                            <h4 class="settings-subtitle">اهداف ماهانه</h4>
                                            @if($monthlyProfitLimit !== null && $monthlyLossLimit !== null)
                                                @php
                                                    $monthlyPnl = isset($monthlyPnlPercent) ? (float)$monthlyPnlPercent : 0.0;
                                                    $monthlyProfitFill = ($monthlyPnl > 0 && $monthlyProfitLimit > 0) ? max(0, min(100, ($monthlyPnl / $monthlyProfitLimit) * 100)) : 0.0;
                                                    $monthlyLossFill = ($monthlyPnl < 0 && $monthlyLossLimit > 0) ? max(0, min(100, (abs($monthlyPnl) / $monthlyLossLimit) * 100)) : 0.0;
                                                @endphp
                                                <div class="muted">ثبت شده و غیرقابل تغییر</div>
                                                <div class="progress-row">
                                                    <div class="progress-box">
                                                        <div class="progress-title"><span>هدف سود</span><span>+{{ number_format($monthlyProfitLimit, 0) }}%</span></div>
                                                        <div class="progress-bar"><div class="progress-fill" style="width: {{ $monthlyProfitFill }}%;"></div></div>
                                                        <div class="muted" style="margin-top:6px;">سود فعلی: {{ number_format(max(0, $monthlyPnl), 1) }}%</div>
                                                    </div>
                                                    <div class="progress-box">
                                                        <div class="progress-title"><span>حد ضرر</span><span>-{{ number_format($monthlyLossLimit, 0) }}%</span></div>
                                                        <div class="progress-bar"><div class="progress-fill loss" style="width: {{ $monthlyLossFill }}%;"></div></div>
                                                        <div class="muted" style="margin-top:6px;">ضرر فعلی: -{{ number_format(max(0, abs(min(0, $monthlyPnl))), 1) }}%</div>
                                                    </div>
                                                </div>
                                                <div class="muted" style="margin-top:8px;">با رسیدن به هر یک از اهداف، معاملات تا پایان ماه قفل می‌شود.</div>
                                            @else
                                                <div class="warning-box">
                                                    با رسیدن به هر یک از اهداف، معاملات تا پایان ماه قفل می‌شود. این تنظیم پس از ثبت غیرقابل تغییر است.
                                                </div>
                                                <div class="form-group">
                                                    <label>
                                                        <input type="checkbox" id="monthly_limit_enabled" name="monthly_limit_enabled" value="1" form="strictTargetsForm" {{ $monthlyLimitEnabled ? 'checked' : '' }}>
                                                        فعال‌سازی محدودیت ماهانه
                                                    </label>
                                                </div>
                                                <div class="form-grid" data-dependent="monthly">
                                                    <div class="form-group">
                                                        <label for="monthly_profit_limit">هدف سود ماهانه (درصد)</label>
                                                        <input type="number" id="monthly_profit_limit" name="monthly_profit_limit" class="form-control" form="strictTargetsForm" min="3" max="60" step="0.1" inputmode="decimal" placeholder="مثال: 25">
                                                    </div>
                                                    <div class="form-group">
                                                        <label for="monthly_loss_limit">حد ضرر ماهانه (درصد)</label>
                                                        <input type="number" id="monthly_loss_limit" name="monthly_loss_limit" class="form-control" form="strictTargetsForm" min="3" max="50" step="0.1" inputmode="decimal" placeholder="مثال: 15">
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                    </div>

                                    <form action="{{ route('account-settings.update-strict-limits') }}" method="POST" id="strictTargetsForm" style="display:none;">
                                        @csrf
                                    </form>

                                    @if(($weeklyProfitLimit === null || $weeklyLossLimit === null) || ($monthlyProfitLimit === null || $monthlyLossLimit === null))
                                        <div class="button-row" style="margin-top:16px;">
                                            <button type="button" class="btn-glass btn-glass-primary" id="openGoalModalBtn">ثبت نهایی اهداف</button>
                                        </div>
                                    @endif
                                @else
                                    <div class="warning-box">
                                        <div style="font-weight:900; margin-bottom:8px;">هشدار مهم</div>
                                        <ul style="margin:0; padding-right:18px;">
                                            <li>این حالت پس از فعال‌سازی قابل بازگشت نیست</li>
                                            <li>فقط در صورت اطمینان کامل این گزینه را فعال کنید</li>
                                            <li>این حالت بر معاملات اسپات تأثیری ندارد</li>
                                        </ul>
                                    </div>
                                    <div class="button-row">
                                        <button type="button" class="btn-glass btn-glass-danger" id="openStrictModeModalBtn">فعال‌سازی حالت سخت‌گیرانه آتی</button>
                                    </div>
                                @endif
                            </div>
                        </section>
                    @endif

                    <section class="account-settings__panel" data-panel="blocking" role="tabpanel">
                        <div class="settings-card">
                            <h3 class="settings-title">مسدودسازی (زمان/قیمت)</h3>

                            <div class="setting-description">
                                اگر احساس می‌کنید شرایط شما یا شرایط بازار برای معامله مناسب نیست، از این قابلیت استفاده کنید تا معاملات جدید به‌صورت احساسی باز نشوند و از ضررهای احتمالی جلوگیری شود.
                            </div>

                            @if(!$strictModeActive)
                                <div class="warning-box">
                                    برای استفاده از مسدودسازی دستی، ابتدا باید حالت سخت‌گیرانه را فعال کنید. فرم‌ها نمایش داده شده‌اند اما تا زمان فعال‌سازی غیرقابل ارسال هستند.
                                </div>
                            @endif

                            @if($strictModeActive && isset($selfBanTime) && $selfBanTime)
                                <div class="alert alert-success">
                                    مسدودسازی زمانی فعال است تا: {{ $selfBanTime->ends_at->format('Y/m/d H:i') }}
                                    @if($selfBanTimeRemainingMinutes !== null)
                                        <div class="muted" style="margin-top:6px;">زمان باقی‌مانده: {{ $selfBanTimeRemainingMinutes }} دقیقه</div>
                                    @endif
                                </div>
                            @endif

                            @if($strictModeActive && isset($selfBanPrice) && $selfBanPrice)
                                <div class="alert alert-success">
                                    مسدودسازی بر اساس قیمت فعال است تا: {{ $selfBanPrice->ends_at->format('Y/m/d H:i') }}
                                    @if($selfBanPriceRemainingMinutes !== null)
                                        <div class="muted" style="margin-top:6px;">زمان باقی‌مانده: {{ $selfBanPriceRemainingMinutes }} دقیقه</div>
                                    @endif
                                    <div class="muted" style="margin-top:6px;">
                                        @if($selfBanPrice->price_below !== null)
                                            قیمت پایین‌تر از: {{ $selfBanPrice->price_below }}
                                        @endif
                                        @if($selfBanPrice->price_above !== null)
                                            @if($selfBanPrice->price_below !== null)
                                                <span style="padding:0 8px;">|</span>
                                            @endif
                                            قیمت بالاتر از: {{ $selfBanPrice->price_above }}
                                        @endif
                                    </div>
                                </div>
                            @endif

                            <fieldset {{ $strictModeActive ? '' : 'disabled' }} style="border:0; padding:0; margin:0;">
                                <div class="split-card">
                                    <div class="card-section">
                                        <h4 class="settings-subtitle">مسدودسازی زمانی</h4>
                                        <form action="{{ route('account-settings.self-ban') }}" method="POST" id="selfBanTimeForm">
                                            @csrf
                                            <input type="hidden" name="self_ban_mode" value="time">
                                            <div class="form-group">
                                                <label for="duration_minutes_time">مدت زمان</label>
                                                <select id="duration_minutes_time" name="duration_minutes" class="form-control" required>
                                                    <option value="30">30 دقیقه</option>
                                                    <option value="60">1 ساعت</option>
                                                    <option value="120">2 ساعت</option>
                                                    <option value="240">4 ساعت</option>
                                                    <option value="720">12 ساعت</option>
                                                    <option value="1440">1 روز</option>
                                                    <option value="2880">2 روز</option>
                                                    <option value="4320">3 روز</option>
                                                    <option value="10080">1 هفته</option>
                                                    <option value="43200">1 ماه</option>
                                                </select>
                                            </div>
                                            <div class="button-row">
                                                <button type="button" class="btn-glass btn-glass-danger" data-trigger-ban="selfBanTimeForm">فعال‌سازی مسدودسازی زمانی</button>
                                            </div>
                                        </form>
                                    </div>

                                    <div class="card-section">
                                        <h4 class="settings-subtitle">مسدودسازی بر اساس قیمت</h4>
                                        <form action="{{ route('account-settings.self-ban') }}" method="POST" id="selfBanPriceForm">
                                            @csrf
                                            <input type="hidden" name="self_ban_mode" value="price">
                                            <div class="form-group">
                                                <label for="duration_minutes_price">حداکثر مدت زمان</label>
                                                <select id="duration_minutes_price" name="duration_minutes" class="form-control" required>
                                                    <option value="30">30 دقیقه</option>
                                                    <option value="60">1 ساعت</option>
                                                    <option value="120">2 ساعت</option>
                                                    <option value="240">4 ساعت</option>
                                                    <option value="720">12 ساعت</option>
                                                    <option value="1440">1 روز</option>
                                                    <option value="2880">2 روز</option>
                                                    <option value="4320">3 روز</option>
                                                    <option value="10080">1 هفته</option>
                                                    <option value="43200">1 ماه</option>
                                                </select>
                                            </div>
                                            <div class="form-group">
                                                <label for="price_below">قیمت پایین‌تر از</label>
                                                <input type="number" id="price_below" name="price_below" class="form-control" inputmode="decimal" placeholder="مثال: 65000" step="0.0001" min="0">
                                            </div>
                                            <div class="form-group">
                                                <label for="price_above">قیمت بالاتر از</label>
                                                <input type="number" id="price_above" name="price_above" class="form-control" inputmode="decimal" placeholder="مثال: 72000" step="0.0001" min="0">
                                            </div>
                                            <div class="button-row">
                                                <button type="button" class="btn-glass btn-glass-danger" data-trigger-ban="selfBanPriceForm">فعال‌سازی مسدودسازی بر اساس قیمت</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </fieldset>
                        </div>
                    </section>
            </div>
            </div>
        </div>
    </div>

    @if($isStrictAdmin && !$strictModeActive)
        <div class="modal" id="strictModeModal" role="dialog" aria-modal="true">
            <div class="modal__dialog">
                <div class="modal__header">
                    <h3 class="modal__title">تأیید فعال‌سازی حالت سخت‌گیرانه</h3>
                    <button type="button" class="btn-glass btn-glass-muted modal__close" id="closeStrictModeModalBtn">&times;</button>
                </div>

                <div class="warning-box">
                    این عمل غیرقابل بازگشت است. لطفاً بازار مورد نظر را انتخاب کنید. پس از فعال‌سازی، تنها می‌توانید در این بازار معامله کنید.
                </div>

                <div class="form-group">
                    <label for="selectedMarket">انتخاب بازار</label>
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
                    <label for="minRrRatio">حداقل نسبت سود به ضرر (RR)</label>
                    <select id="minRrRatio" class="form-control">
                        <option value="1:3">1:3 - سود یک‌سوم ضرر</option>
                        <option value="1:2">1:2 - سود نصف ضرر</option>
                        <option value="1:1">1:1 - سود برابر با ضرر</option>
                        <option value="2:1">2:1 - سود ۲ برابر ضرر</option>
                    </select>
                </div>

                <div class="button-row">
                    <button type="button" class="btn-glass btn-glass-muted" id="cancelStrictModeBtn">انصراف</button>
                    <button type="button" class="btn-glass btn-glass-danger" id="confirmStrictModeBtn">تأیید و فعال‌سازی</button>
                </div>
            </div>
        </div>
    @endif

    <div class="modal" id="selfBanConfirmModal" role="dialog" aria-modal="true">
        <div class="modal__dialog">
            <div class="modal__header">
                <h3 class="modal__title">تأیید مسدودسازی حساب</h3>
                <button type="button" class="btn-glass btn-glass-muted modal__close" id="closeSelfBanModalBtn">&times;</button>
            </div>

            <div class="warning-box">
                <strong>توجه مهم:</strong> پس از فعال‌سازی مسدودسازی، امکان لغو آن به هیچ عنوان وجود ندارد و شما باید تا پایان زمان مشخص شده منتظر بمانید. آیا از این عمل اطمینان دارید؟
            </div>

            <div class="button-row">
                <button type="button" class="btn-glass btn-glass-muted" id="cancelSelfBanBtn">انصراف</button>
                <button type="button" class="btn-glass btn-glass-danger" id="confirmSelfBanBtn">تأیید و فعال‌سازی مسدودسازی</button>
            </div>
        </div>
    </div>
    <div class="modal" id="goalConfirmModal" role="dialog" aria-modal="true">
        <div class="modal__dialog">
            <div class="modal__header">
                <h3 class="modal__title">تأیید ثبت اهداف</h3>
                <button type="button" class="btn-glass btn-glass-muted modal__close" id="closeGoalModalBtn">&times;</button>
            </div>

            <div class="warning-box">
                ایا از ثبت این اهداف اطمینان دارید؟این عملیات پس از تایید غیر قابل بازگشت خواهد بود و با رسیدن به هرکدام از اهداف ،معاملات شما تا اخر آن دوره قفل خواهد شد.
            </div>

            <div class="button-row">
                <button type="button" class="btn-glass btn-glass-muted" id="cancelGoalBtn">انصراف</button>
                <button type="button" class="btn-glass btn-glass-danger" id="confirmGoalBtn">تأیید و ثبت اهداف</button>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            function setHtml(el, html) {
                if (!el) return;
                el.innerHTML = html;
            }

            function showAlert(message, type) {
                const alertContainer = document.getElementById('alert-container');
                const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
                setHtml(alertContainer, `<div class="alert ${alertClass}">${message}</div>`);
                setTimeout(function () {
                    setHtml(alertContainer, '');
                }, 5000);
            }

            document.addEventListener('DOMContentLoaded', function () {
                const root = document.getElementById('accountSettingsRoot');
                if (root) {
                    const tabs = Array.from(root.querySelectorAll('[data-tab]'));
                    const panels = Array.from(root.querySelectorAll('[data-panel]'));

                    function activateTab(tabId) {
                        if (!tabId) return;
                        tabs.forEach(function (t) {
                            const active = t.getAttribute('data-tab') === tabId;
                            t.setAttribute('aria-selected', active ? 'true' : 'false');
                            
                            // Auto-scroll for mobile
                            if (active && window.innerWidth <= 820) {
                                t.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
                            }
                        });
                        panels.forEach(function (p) {
                            p.classList.toggle('is-active', p.getAttribute('data-panel') === tabId);
                        });
                        try { localStorage.setItem('accountSettingsActiveTab', tabId); } catch (e) {}
                        if (history && history.replaceState) {
                            try { history.replaceState(null, '', '#' + tabId); } catch (e) {}
                        }
                    }

                    tabs.forEach(function (t) {
                        t.addEventListener('click', function () {
                            activateTab(t.getAttribute('data-tab'));
                        });
                    });

                    const hashTab = (window.location.hash || '').replace('#', '');
                    let initialTab = hashTab || null;
                    if (!initialTab) {
                        try { initialTab = localStorage.getItem('accountSettingsActiveTab'); } catch (e) {}
                    }
                    if (!initialTab || !panels.some(function (p) { return p.getAttribute('data-panel') === initialTab; })) {
                        initialTab = tabs[0] ? tabs[0].getAttribute('data-tab') : null;
                    }
                    activateTab(initialTab);
                }

                function setupDependentToggle(toggleId, dependentKey) {
                    const toggle = document.getElementById(toggleId);
                    const wrappers = Array.from(document.querySelectorAll('[data-dependent="' + dependentKey + '"]'));
                    if (!toggle || wrappers.length === 0) return;

                    function setState(enabled) {
                        wrappers.forEach(function (w) {
                            const inputs = Array.from(w.querySelectorAll('input, select, textarea'));
                            inputs.forEach(function (i) {
                                i.disabled = !enabled;
                                if (i.type === 'number') {
                                    i.required = enabled;
                                }
                            });
                            w.style.opacity = enabled ? '1' : '0.55';
                        });
                    }

                    toggle.addEventListener('change', function () {
                        setState(toggle.checked);
                    });
                    setState(!!toggle.checked);
                }

                setupDependentToggle('weekly_limit_enabled', 'weekly');
                setupDependentToggle('monthly_limit_enabled', 'monthly');

                const modal = document.getElementById('strictModeModal');
                const openBtn = document.getElementById('openStrictModeModalBtn');
                const closeBtn = document.getElementById('closeStrictModeModalBtn');
                const cancelBtn = document.getElementById('cancelStrictModeBtn');
                const confirmBtn = document.getElementById('confirmStrictModeBtn');

                function closeModal() {
                    if (!modal) return;
                    modal.classList.remove('is-open');
                }

                function openModal() {
                    if (!modal) return;
                    modal.classList.add('is-open');
                }

                if (openBtn && modal) openBtn.addEventListener('click', openModal);
                if (closeBtn) closeBtn.addEventListener('click', closeModal);
                if (cancelBtn) cancelBtn.addEventListener('click', closeModal);
                if (modal) {
                    modal.addEventListener('click', function (e) {
                        if (e.target === modal) closeModal();
                    });
                }

                if (confirmBtn) {
                    confirmBtn.addEventListener('click', function () {
                        const selectedMarketEl = document.getElementById('selectedMarket');
                        const minRrRatioEl = document.getElementById('minRrRatio');
                        const selectedMarket = selectedMarketEl ? selectedMarketEl.value : '';
                        const minRrRatio = minRrRatioEl ? (minRrRatioEl.value || '3:1') : '3:1';

                        if (!selectedMarket) {
                            showAlert('لطفاً ابتدا بازار مورد نظر را انتخاب کنید', 'danger');
                            return;
                        }

                        const originalText = confirmBtn.textContent;
                        confirmBtn.textContent = 'در حال پردازش...';
                        confirmBtn.disabled = true;

                        fetch('{{ route("settings.activate-future-strict-mode") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({ selected_market: selectedMarket, min_rr_ratio: minRrRatio })
                        })
                            .then(function (response) {
                                if (!response.ok) throw new Error('HTTP ' + response.status);
                                return response.json();
                            })
                            .then(function (data) {
                                if (data && data.success) {
                                    showAlert(data.message || 'با موفقیت انجام شد', 'success');
                                    setTimeout(function () { window.location.reload(); }, 4000);
                                } else {
                                    showAlert((data && data.message) ? data.message : 'خطای نامشخص رخ داده است', 'danger');
                                    confirmBtn.textContent = originalText;
                                    confirmBtn.disabled = false;
                                }
                                closeModal();
                            })
                            .catch(function () {
                                showAlert('خطا در برقراری ارتباط با سرور', 'danger');
                                confirmBtn.textContent = originalText;
                                confirmBtn.disabled = false;
                                closeModal();
                            });
                    });
                }

                // Self Ban Confirmation Logic
                const selfBanModal = document.getElementById('selfBanConfirmModal');
                const closeSelfBanBtn = document.getElementById('closeSelfBanModalBtn');
                const cancelSelfBanBtn = document.getElementById('cancelSelfBanBtn');
                const confirmSelfBanBtn = document.getElementById('confirmSelfBanBtn');
                const banTriggers = Array.from(document.querySelectorAll('[data-trigger-ban]'));
                let activeBanForm = null;

                function closeSelfBanModal() {
                    if (selfBanModal) selfBanModal.classList.remove('is-open');
                    activeBanForm = null;
                }

                banTriggers.forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        activeBanForm = document.getElementById(btn.getAttribute('data-trigger-ban'));
                        if (selfBanModal) selfBanModal.classList.add('is-open');
                    });
                });

                if (closeSelfBanBtn) closeSelfBanBtn.addEventListener('click', closeSelfBanModal);
                if (cancelSelfBanBtn) cancelSelfBanBtn.addEventListener('click', closeSelfBanModal);
                if (selfBanModal) {
                    selfBanModal.addEventListener('click', function (e) {
                        if (e.target === selfBanModal) closeSelfBanModal();
                    });
                }

                if (confirmSelfBanBtn) {
                    confirmSelfBanBtn.addEventListener('click', function () {
                        if (activeBanForm) {
                            confirmSelfBanBtn.disabled = true;
                            confirmSelfBanBtn.textContent = 'در حال فعال‌سازی...';
                            activeBanForm.submit();
                        }
                    });
                }

                // Goal Confirmation Logic
                const goalModal = document.getElementById('goalConfirmModal');
                const openGoalBtn = document.getElementById('openGoalModalBtn');
                const closeGoalBtn = document.getElementById('closeGoalModalBtn');
                const cancelGoalBtn = document.getElementById('cancelGoalBtn');
                const confirmGoalBtn = document.getElementById('confirmGoalBtn');
                const goalForm = document.getElementById('strictTargetsForm');

                function closeGoalModal() {
                    if (goalModal) goalModal.classList.remove('is-open');
                }

                if (openGoalBtn) {
                    openGoalBtn.addEventListener('click', function() {
                        if (goalModal) goalModal.classList.add('is-open');
                    });
                }

                if (closeGoalBtn) closeGoalBtn.addEventListener('click', closeGoalModal);
                if (cancelGoalBtn) cancelGoalBtn.addEventListener('click', closeGoalModal);
                if (goalModal) {
                    goalModal.addEventListener('click', function (e) {
                        if (e.target === goalModal) closeGoalModal();
                    });
                }

                if (confirmGoalBtn) {
                    confirmGoalBtn.addEventListener('click', function () {
                        if (goalForm) {
                            confirmGoalBtn.disabled = true;
                            confirmGoalBtn.textContent = 'در حال ثبت...';
                            goalForm.submit();
                        }
                    });
                }

                const isStrictActive = {{ $strictModeActive ? 'true' : 'false' }};
                 if (isStrictActive) {
                    const riskInput = document.getElementById('default_risk');
                    if (riskInput) {
                        riskInput.max = '10';
                        riskInput.addEventListener('input', function () {
                            const value = parseFloat(riskInput.value || '0');
                            if (value > 10) {
                                riskInput.setCustomValidity('در حالت سخت‌گیرانه حداکثر ۱۰ درصد مجاز است');
                            } else {
                                riskInput.setCustomValidity('');
                            }
                        });
                    }
                }
            });
        })();
    </script>
@endpush
