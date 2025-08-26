@extends('layouts.app')

@section('title', 'پروفایل')

@push('styles')
<style>
    .container {
        width: 100%;
        max-width: 800px;
        margin: auto;
    }
    .profile-card {
        background: #ffffff;
        padding: 30px;
        border-radius: 15px;
        box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        text-align: center;
        margin-bottom: 20px;
    }
    .profile-card h2 {
        margin-bottom: 10px;
    }
    .profile-card .username {
        font-size: 1.5em;
        font-weight: bold;
        color: var(--primary-color);
        margin-bottom: 20px;
    }
    .profile-card .equity {
        font-size: 1.2em;
        margin-bottom: 30px;
    }
    .profile-card .equity strong {
        font-size: 1.5em;
        color: #28a745;
    }
    
    /* Exchange section styles */
    .exchange-section {
        background: #ffffff;
        padding: 25px;
        border-radius: 15px;
        box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        margin-bottom: 20px;
    }
    
    .current-exchange {
        border-left: 5px solid var(--exchange-color, #007bff);
        padding: 20px;
        @if($currentExchange)
        background: linear-gradient(135deg, rgba({{ $currentExchange->exchange_color_rgb }}, 0.1), #ffffff);
        @else
        background: linear-gradient(135deg, rgba(0, 123, 255, 0.1), #ffffff);
        @endif
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
        border: 2px solid #e9ecef;
        border-radius: 10px;
        padding: 15px;
        transition: all 0.3s;
        cursor: pointer;
        background: white;
    }
    
    .exchange-option:hover {
        border-color: var(--exchange-color, #007bff);
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }
    
    .exchange-option.current {
        border-color: var(--exchange-color, #007bff);
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
        background: #f8f9fa;
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
    
    /* Mobile Responsive Styles */
    @media (max-width: 768px) {
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
        
        .admin-panel-section {
            margin: 15px 0 !important;
            padding: 15px !important;
        }
        
        .admin-panel-section h3 {
            font-size: 1em !important;
            margin-bottom: 10px !important;
        }
        
        .admin-buttons {
            grid-template-columns: 1fr !important;
            gap: 8px !important;
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
<div class="container">
    <div class="profile-card">
        <h2>پروفایل کاربری</h2>
        
        @if(session('success'))
            <div class="alert alert-success">
                {{ session('success') }}
            </div>
        @endif
        
        <div class="username">{{ $user->email }}</div>
        
        @if($currentExchange)
            <div class="equity">
                <p>موجودی لحظه ای حساب ({{ $currentExchange->exchange_display_name }}): <strong>{{ $totalEquity }}$</strong></p>
                <p>موجودی کیف پول: <strong>{{ $totalBalance }}$</strong></p>
            </div>
        @else
            <div class="equity">
                <p style="color: #dc3545;">هیچ صرافی فعالی تنظیم نشده است</p>
            </div>
        @endif
        
        <div class="profile-buttons">
            <a href="{{ route('password.change.form') }}" class="btn btn-primary">
                تغییر رمز عبور
            </a>
            <a href="{{ route('settings.index') }}" class="btn btn-success">
                تنظیمات
            </a>
        </div>
        
        @if(auth()->user()->isAdmin())
        <div class="admin-panel-section" style="margin: 20px 0; padding: 20px; background: #f8f9fa; border-radius: 10px; border-left: 4px solid #6f42c1;">
            <h3 style="margin: 0 0 15px 0; color: #6f42c1; font-size: 1.1em;">🛠️ پنل مدیریت</h3>
            <div class="admin-buttons" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px;">
                <a href="{{ route('admin.all-users') }}" class="btn" style="background-color: #6f42c1; color: white; margin: 0;">
                    👥 همه کاربران
                </a>
                <a href="{{ route('admin.pending-exchanges') }}" class="btn" style="background-color: #fd7e14; color: white; margin: 0;">
                    ⏳ درخواست‌های صرافی
                </a>
                <a href="{{ route('admin.all-exchanges') }}" class="btn" style="background-color: #20c997; color: white; margin: 0;">
                    🏦 همه صرافی‌ها
                </a>
            </div>
        </div>
        @endif
        
        <a href="#" onclick="event.preventDefault(); document.getElementById('logout-form').submit();" class="btn btn-danger">
            خروج از حساب
        </a>
    </div>
    
    <!-- Current Exchange Display -->
    @if($currentExchange)
        <div class="exchange-section">
            <h3 style="text-align: center; margin-bottom: 20px;">صرافی فعال شما</h3>
            
            <div class="current-exchange" style="--exchange-color: {{ $currentExchange->exchange_color }}">
                <div class="exchange-header">
                    <div class="exchange-info">
                        <div class="exchange-logo">
                            {{ substr($currentExchange->exchange_display_name, 0, 2) }}
                        </div>
                        <div class="exchange-details">
                            <h3>{{ $currentExchange->exchange_display_name }}</h3>
                            <div class="exchange-status">
                                صرافی پیش‌فرض شما • کلید API: {{ $currentExchange->masked_api_key }}
                            </div>
                        </div>
                    </div>
                    <div>
                        <a href="{{ route('exchanges.index') }}" class="btn btn-primary">
                            مدیریت صرافی‌ها
                        </a>
                    </div>
                </div>
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
    
    <!-- Quick Exchange Switching -->
    @if($activeExchanges->count() > 1)
        <div class="exchange-section">
            <div class="quick-switch">
                <h4>تغییر سریع صرافی</h4>
                <div class="exchange-grid">
                    @foreach($activeExchanges as $exchange)
                        <div class="exchange-option {{ $exchange->is_default ? 'current' : '' }}" 
                             style="--exchange-color: {{ $exchange->exchange_color ?? '#007bff' }}; {{ $exchange->is_default ? 'background: linear-gradient(135deg, rgba(' . ($exchange->exchange_color_rgb ?? '0, 123, 255') . ', 0.15), #ffffff);' : '' }}"
                             onclick="switchExchange({{ $exchange->id }})">
                            <div class="mini-logo">
                                {{ substr($exchange->exchange_display_name, 0, 2) }}
                            </div>
                            <div class="name">{{ $exchange->exchange_display_name }}</div>
                            <div class="status">
                                {{ $exchange->is_default ? 'فعال' : 'کلیک برای تغییر' }}
                            </div>
                        </div>
                    @endforeach
                </div>
                <a href="{{ route('exchanges.index') }}" class="btn btn-primary">
                    مشاهده همه صرافی‌ها
                </a>
            </div>
        </div>
    @endif
</div>

<script>
function switchExchange(exchangeId) {
    if (confirm('آیا می‌خواهید به این صرافی تغییر دهید؟')) {
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
    }
}
</script>
@endsection
