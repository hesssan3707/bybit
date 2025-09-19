@extends('layouts.app')

@section('title', 'پروفایل')

@push('styles')
    <style>
        /* Base Styles */
        .container {
            width: 100%;
            max-width: 800px;
            margin: auto;
            padding: 20px;
        }

        /* Common Button Styles */
        .btn {
            display: inline-block;
            padding: 10px 20px;
            margin: 5px;
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }
        .btn:hover {
            opacity: 0.8;
            transform: translateY(-1px);
        }

        /* Button Color Variants */
        .btn-primary { background-color: var(--primary-color); color: white; }
        .btn-danger { background-color: #dc3545; color: white; }
        .btn-success { background-color: #28a745; color: white; }

        /* Text Color Utilities */
        .text-success { color: #28a745 !important; }
        .text-primary { color: #007bff !important; }
        .text-danger { color: #dc3545 !important; }

        /* Alert Styles */
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

        /* Exchange section styles */
        .exchange-section {
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.3);
            margin-bottom: 20px;
        }

        .current-exchange {
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .exchange-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 15px;
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
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: var(--exchange-color, #007bff);
            font-size: 18px;
            border: 2px solid var(--exchange-color, #007bff);
        }

        .exchange-details h3 {
            margin: 0;
            font-size: 1.4em;
            color: var(--exchange-color, #007bff);
        }

        .exchange-status {
            font-size: 0.9em;
            color: #666;
            margin-top: 5px;
        }

        .quick-switch {
            text-align: center;
        }

        .quick-switch h4 {
            margin-bottom: 15px;
            color: #333;
        }

        .exchange-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }

        .exchange-option {
            box-shadow: 0 8px 25px rgba(0,0,0,0.3);
            border-radius: 10px;
            padding: 15px;
            transition: all 0.3s;
            cursor: pointer;
        }
        .exchange-option .current{
            border : rgba(129, 125, 125, 0.62) 2px solid;
        }

        .exchange-option:hover {
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .exchange-option .mini-logo {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: var(--exchange-color, #007bff);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 12px;
            margin: 0 auto 10px;
        }

        .exchange-option .name {
            font-weight: bold;
            margin-bottom: 5px;
            color: var(--exchange-color, #007bff);
        }

        .exchange-option .status {
            font-size: 0.8em;
            color: #666;
        }

        .btn {
            display: inline-block;
            padding: 10px 20px;
            margin: 5px;
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
            transition: opacity 0.3s;
            border: none;
            cursor: pointer;
        }
        .btn:hover {
            opacity: 0.8;
        }

        /* Profile Information Boxes */
        .profile-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .info-box {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 0;
            overflow: hidden;
            transition: all 0.3s ease;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }

        .info-box:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.2);
            border-color: rgba(255, 255, 255, 0.3);
        }

        .info-box-header {
            background: linear-gradient(135deg, rgba(0, 123, 255, 0.8), rgba(0, 86, 179, 0.8));
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .info-box-header i {
            font-size: 24px;
            color: white;
        }

        .info-box-header h3 {
            color: white;
            margin: 0;
            font-size: 16px;
            font-weight: 600;
        }

        .info-box-content {
            padding: 25px;
        }

        .profile-detail {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding: 10px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .profile-detail:last-child {
            margin-bottom: 0;
            border-bottom: none;
        }

        .profile-detail .label {
            color: rgba(255, 255, 255, 0.7);
            font-size: 14px;
            font-weight: 500;
        }

        .profile-detail .value {
            color: white;
            font-size: 14px;
            font-weight: 600;
            direction: ltr;
            text-align: left;
        }

        .balance-amount {
            display: flex;
            align-items: baseline;
            gap: 5px;
            margin-bottom: 15px;
        }

        .balance-amount .currency {
            color: rgba(255, 255, 255, 0.7);
            font-size: 18px;
            font-weight: 500;
        }

        .balance-amount .amount {
            color: #28a745;
            font-size: 28px;
            font-weight: 700;
            text-shadow: 0 2px 4px rgba(40, 167, 69, 0.3);
        }

        .balance-status {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .balance-status i {
            font-size: 12px;
        }

        .balance-status span {
            color: rgba(255, 255, 255, 0.8);
            font-size: 13px;
            font-weight: 500;
        }

        .text-success {
            color: #28a745 !important;
        }

        .text-primary {
            color: #007bff !important;
        }

        .text-danger {
            color: #dc3545 !important;
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
        .no-exchange {
            text-align: center;
            padding: 30px;
            color: #666;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        /* Profile buttons styling */
        .profile-buttons {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 15px;
            margin: 20px 0;
            flex-wrap: wrap;
        }

        .actions-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
        }

        .actions-box .info-box-header {
            color: white;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }

        .actions-box .info-box-header i {
            color: white;
        }

        .actions-box .info-box-content {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
        }

        .action-buttons-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            padding: 10px 0;
        }

        .action-btn {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 16px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            border: 2px solid transparent;
            background: rgba(255, 255, 255, 0.9);
            color: #333;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            text-decoration: none;
        }

        .action-btn.btn-primary {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
        }

        .action-btn.btn-primary:hover {
            background: linear-gradient(135deg, #0056b3, #004085);
            color: white;
        }

        .action-btn.btn-success {
            background: linear-gradient(135deg, #28a745, #1e7e34);
            color: white;
        }

        .action-btn.btn-success:hover {
            background: linear-gradient(135deg, #1e7e34, #155724);
            color: white;
        }

        .action-btn.btn-admin {
            background: linear-gradient(135deg, #6f42c1, #5a32a3);
            color: white;
        }

        .action-btn.btn-admin:hover {
            background: linear-gradient(135deg, #5a32a3, #4c2a85);
            color: white;
        }

        .action-btn.btn-danger {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
        }

        .action-btn.btn-danger:hover {
            background: linear-gradient(135deg, #c82333, #bd2130);
            color: white;
        }

        .action-btn i {
            font-size: 16px;
            min-width: 16px;
        }

        .action-btn span {
            font-size: 14px;
        }

        /* Hide admin panel button on desktop */
        .admin-panel-btn {
            display: none;
        }

        /* Mobile Responsive Styles */
        @media (max-width: 768px) {
            .profile-info-grid {
                grid-template-columns: 1fr;
                gap: 15px;
                margin-bottom: 20px;
            }

            .info-box-header {
                padding: 15px;
            }

            .info-box-header h3 {
                font-size: 14px;
            }

            .info-box-content {
                padding: 20px;
            }

            .balance-amount .amount {
                font-size: 24px;
            }

            .profile-detail {
                flex-direction: column;
                align-items: flex-start;
                gap: 5px;
            }

            .profile-detail .value {
                text-align: right;
                direction: rtl;
            }
            .container {
                padding: 0;
                margin: 0 auto;
                width: 100%;
            }

            .profile-card {
                padding: 20px;
                margin: 10px;
                width: calc(100% - 20px);
                box-sizing: border-box;
            }

            .profile-card .username {
                font-size: 1.3em;
                margin-bottom: 15px;
            }

            .profile-card .equity {
                font-size: 1em;
                margin-bottom: 20px;
            }

            .profile-card .equity strong {
                font-size: 1.3em;
            }

            .exchange-section {
                padding: 15px;
                margin: 10px;
                width: calc(100% - 20px);
                box-sizing: border-box;
            }

            .current-exchange {
                padding: 15px;
                margin-bottom: 15px;
            }

            .exchange-header {
                flex-direction: column;
                align-items: stretch;
                gap: 15px;
            }

            .exchange-info {
                flex-direction: column;
                align-items: center;
                text-align: center;
            }

            .exchange-logo {
                width: 40px;
                height: 40px;
                margin: 0 0 10px 0;
                font-size: 16px;
            }

            .exchange-details h3 {
                font-size: 1.2em;
                margin-bottom: 5px;
            }

            .exchange-status {
                font-size: 0.85em;
                margin-bottom: 15px;
            }

            .exchange-grid {
                grid-template-columns: 1fr;
                gap: 10px;
                margin-bottom: 10px;
            }

            .exchange-option {
                padding: 12px;
                text-align: center;
            }
            .exchange-option .current{
                border : rgba(129, 125, 125, 0.62) 2px solid;
            }

            .exchange-option .mini-logo {
                width: 25px;
                height: 25px;
                font-size: 10px;
                margin: 0 auto 8px;
            }

            .exchange-option .name {
                font-size: 0.9em;
                margin-bottom: 3px;
            }

            .exchange-option .status {
                font-size: 0.75em;
            }

            .btn {
                padding: 12px 16px;
                margin: 3px;
                font-size: 0.9em;
                flex: 1;
                min-width: calc(50% - 10px);
                max-width: 200px;
                box-sizing: border-box;
                text-align: center;
            }

            .profile-buttons {
                flex-direction: column;
                gap: 10px;
                margin: 15px 0;
            }

            .profile-buttons .btn {
                width: 100%;
                max-width: none;
                flex: none;
            }

            .action-buttons-grid {
                grid-template-columns: 1fr;
                gap: 10px;
            }

            .action-btn {
                padding: 10px 12px;
                font-size: 13px;
            }

            .action-btn i {
                font-size: 14px;
            }

            .action-btn span {
                font-size: 13px;
            }

            /* Show admin panel button on mobile */
            .admin-panel-btn {
                display: inline-block !important;
            }

            .no-exchange {
                padding: 20px;
            }

            .no-exchange h3 {
                font-size: 1.1em;
                margin-bottom: 10px;
            }

            .no-exchange p {
                font-size: 0.9em;
                margin-bottom: 15px;
            }
        }

        /* Extra small screens */
        @media (max-width: 480px) {
            .info-box-header {
                padding: 12px;
            }

            .info-box-content {
                padding: 15px;
            }

            .balance-amount .amount {
                font-size: 20px;
            }
            .container {
                padding: 0;
                margin: 0 auto;
            }

            .profile-card {
                padding: 15px;
                margin: 5px;
                width: calc(100% - 10px);
            }

            .profile-card .username {
                font-size: 1.2em;
            }

            .exchange-section {
                padding: 10px;
                margin: 5px;
                width: calc(100% - 10px);
            }

            .current-exchange {
                padding: 10px;
            }

            .exchange-logo {
                width: 35px;
                height: 35px;
                font-size: 14px;
            }

            .exchange-details h3 {
                font-size: 1.1em;
            }

            .exchange-status {
                font-size: 0.8em;
            }

            .btn {
                padding: 10px 12px;
                font-size: 0.85em;
            }
        }
    </style>
@endpush

@section('content')
    <div class="glass-card container">
        <!-- Profile Information Boxes -->
        <div class="profile-info-grid">
            <div class="info-box user-profile-box">
                 <div class="info-box-header">
                     <i class="fas fa-user-circle"></i>
                     <h3>پروفایل کاربری</h3>
                 </div>
                 <div class="info-box-content">
                     <div class="profile-detail">
                         <span class="label">ایمیل:</span>
                         <span class="value">{{ $user->email }}</span>
                     </div>
                     
                     <div class="action-buttons-grid" style="margin-top: 20px;">
                         <a href="{{ route('password.change.form') }}" class="action-btn btn-primary">
                             <i class="fas fa-key"></i>
                             <span>تغییر رمز عبور</span>
                         </a>
                         <a href="{{ route('account-settings.index') }}" class="action-btn btn-success">
                             <i class="fas fa-cog"></i>
                             <span>تنظیمات</span>
                         </a>
                         <a href="{{ route('exchanges.index') }}" class="action-btn btn-primary">
                             <i class="fas fa-exchange-alt"></i>
                             <span>مدیریت صرافی‌ها</span>
                         </a>
                         @if(auth()->user()->isAdmin())
                             <a href="{{ route('admin.all-users') }}" class="action-btn btn-admin">
                                 <i class="fas fa-user-shield"></i>
                                 <span>پنل مدیریت</span>
                             </a>
                         @endif
                         <a href="#" onclick="event.preventDefault(); document.getElementById('logout-form').submit();" class="action-btn btn-danger">
                             <i class="fas fa-sign-out-alt"></i>
                             <span>خروج از حساب</span>
                         </a>
                     </div>
                 </div>
             </div>

            @if($currentExchange)
                <div class="info-box balance-box">
                    <div class="info-box-header">
                        <i class="fas fa-wallet"></i>
                        <h3>موجودی لحظه‌ای حساب ({{ $currentExchange->exchange_display_name }})</h3>
                    </div>
                    <div class="info-box-content">
                        <div class="balance-amount">
                            <span class="currency">$</span>
                            <span class="amount">{{ $totalEquity }}</span>
                        </div>
                        <div class="balance-status">
                            <i class="fas fa-circle text-success"></i>
                            <span>آنلاین</span>
                        </div>
                    </div>
                </div>

                <div class="info-box wallet-box">
                    <div class="info-box-header">
                        <i class="fas fa-coins"></i>
                        <h3>موجودی کیف پول ({{ $currentExchange->exchange_display_name }})</h3>
                    </div>
                    <div class="info-box-content">
                        <div class="balance-amount">
                            <span class="currency">$</span>
                            <span class="amount">{{ $totalBalance }}</span>
                        </div>
                        <div class="balance-status">
                            <i class="fas fa-sync-alt text-primary"></i>
                            <span>به‌روزرسانی شده</span>
                        </div>
                    </div>
                </div>
            @else
                <div class="info-box balance-box">
                    <div class="info-box-header">
                        <i class="fas fa-exclamation-triangle"></i>
                        <h3>وضعیت صرافی</h3>
                    </div>
                    <div class="info-box-content">
                        <div class="balance-status">
                            <i class="fas fa-times-circle text-danger"></i>
                            <span style="color: #dc3545;">هیچ صرافی فعالی تنظیم نشده است</span>
                        </div>
                    </div>
                </div>
            @endif
         </div>

        <!-- Current Exchange Display -->
        @if($activeExchanges->count() == 1)
            <div class="exchange-section">
                <h3 style="text-align: center; margin-bottom: 20px;">صرافی فعال شما</h3>

                <div class="current-exchange" style="--exchange-color: {{ $currentExchange->exchange_color }}">
                    <div class="exchange-header">
                        <div class="exchange-info">
                            <img src="{{ asset('public/logos/' . strtolower($currentExchange->exchange_display_name) . '-logo.png') }}" alt="{{ subStr($currentExchange->exchange_display_name , 0 , 2) }}" class="exchange-logo" style="background-color: {{ $currentExchange->exchange_color }};">
                            <div class="exchange-details">
                                <h3>{{ $currentExchange->exchange_display_name }}</h3>
                                <div class="exchange-status">
                                    صرافی پیش‌فرض شما • کلید API: {{ $currentExchange->masked_api_key }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @elseif($activeExchanges->count() > 1)
            <div class="quick-switch">
                <h3 style="text-align: center; margin-bottom: 20px;">صرافی های شما</h3>
                <div class="exchange-grid">
                    @foreach($activeExchanges as $exchange)
                        <div class="exchange-option {{ $exchange->is_default ? 'current' : '' }}"
                             onclick="switchExchange({{ $exchange->id }})">
                            <div class="mini-logo">
                                <img src="{{ asset('public/logos/' . strtolower($exchange->exchange_display_name) . '-logo.png') }}" alt="{{ subStr($exchange->exchange_display_name , 0 , 2) }}" class="exchange-logo" style="background-color: {{ $exchange->exchange_color }};">
                            </div>
                            <div class="name">{{ $exchange->exchange_display_name }}</div>
                            <div class="status">
                                {{ $exchange->is_default ? 'فعال' : 'انتخاب به عنوان صرافی فعال' }}
                            </div>
                        </div>
                    @endforeach
                 </div>
             </div>
        @else
            <div class="exchange-section">
                <div class="no-exchange">
                    <h3>صرافی فعالی ندارید</h3>
                    <p>برای شروع معاملات، ابتدا یک صرافی اضافه کنید و آن را تأیید کنید</p>
                    <a href="{{ route('exchanges.create') }}" class="btn btn-success">
                        افزودن صرافی
                    </a>
                </div>
            </div>
        @endif
    </div>

    @include('partials.alert-modal')

     <!-- Logout Form -->
     <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
         @csrf
     </form>

     <script>
        function switchExchange(exchangeId) {
            modernConfirm(
                'آیا می‌خواهید به این صرافی تغییر دهید؟',
                function() {
                    try {
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.action = `/exchanges/${exchangeId}/switch`;

                        const csrfInput = document.createElement('input');
                        csrfInput.type = 'hidden';
                        csrfInput.name = '_token';
                        csrfInput.value = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

                        form.appendChild(csrfInput);
                        document.body.appendChild(form);
                        form.submit();
                    } catch (error) {
                        console.error('Error switching exchange:', error);
                        modernAlert('خطا در تغییر صرافی. لطفاً دوباره تلاش کنید.', 'error');
                    }
                },
                'تغییر صرافی'
            );
        }
    </script>
@endsection
