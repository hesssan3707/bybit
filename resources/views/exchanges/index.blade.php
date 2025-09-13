@extends('layouts.app')

@section('title', 'مدیریت صرافی‌ها')

@push('styles')
<style>
    .container {
        width: 100%;
        max-width: 1000px;
        margin: auto;
    }
    .exchanges-header {
        padding: 20px;
        margin-bottom: 20px;
        text-align: center;
        color : #ffffff;
    }
    .exchange-card {
        padding:20px;
        border-radius: 15px;
        box-shadow: 0 8px 25px rgba(0,0,0,0.4);
        margin-bottom: 20px;
        overflow: hidden;
    }
    .exchange-info {
        display: flex;
        align-items: center;
    }
    .exchange-logo {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        margin-left: 15px;
        background: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        color: var(--exchange-color, #007bff);
    }
    .exchange-details {
        padding: 20px;
    }
    .status-badge {
        display: inline-block;
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: bold;
        text-transform: uppercase;
    }
    .status-active {
        background-color: #d1e7dd;
        color: #0f5132;
    }
    .status-pending {
        background-color: #fff3cd;
        color: #664d03;
    }
    .status-rejected {
        background-color: #f8d7da;
        color: #842029;
    }
    .status-suspended {
        background-color: #e2e3e5;
        color: #41464b;
    }
    .default-badge {
        background-color: #cff4fc;
        color: #055160;
        margin-right: 10px;
    }
    .btn {
        display: inline-block;
        padding: 8px 16px;
        margin: 4px;
        text-decoration: none;
        border-radius: 6px;
        font-size: 14px;
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
    .btn-success {
        background-color: #28a745;
        color: white;
    }
    .btn-warning {
        background-color: #ffc107;
        color: black;
    }
    .btn-info {
        background-color: #17a2b8;
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
    .alert-error {
        background-color: #f8d7da;
        color: #842029;
        border: 1px solid #f5c2c7;
    }
    .empty-state {
        text-align: center;
        padding: 40px 20px;
        color: #666;
    }
    .masked-key {
        font-family: monospace;
        background: #f8f9fa;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 12px;
    }
</style>
@endpush

@section('content')
<div class="glass-card container">
    <div class="exchanges-header">
        <h2>مدیریت صرافی‌ها</h2>
    </div>

    @if(session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-error">
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <div style="margin-bottom: 20px; text-align: center;">
        <a href="{{ route('exchanges.create') }}" class="btn btn-primary">
            + افزودن صرافی جدید
        </a>
    </div>

    @if($exchanges->count() > 0)
        @foreach($exchanges as $exchange)
            <div class="exchange-card" style="--exchange-color: {{ $exchange->exchange_color }}">
                <div class="exchange-header">
                    <div class="exchange-info">
                        <img src="{{ asset('public/logos/' . strtolower($exchange->exchange_display_name) . '-logo.png') }}" alt="{{ subStr($exchange->exchange_display_name , 0 , 2) }}" class="exchange-logo" style="background-color: {{ $exchange->exchange_color }};">
                        <div>
                            <h3 style="margin: 0; color: white;">{{ $exchange->exchange_display_name }}</h3>
                            <div style="margin-top: 5px;">
                                <span class="status-badge status-{{ $exchange->status === 'approved' && $exchange->is_active ? 'active' : $exchange->status }}">
                                    @if($exchange->status === 'approved' && $exchange->is_active)
                                        فعال
                                    @elseif($exchange->status === 'pending')
                                        در انتظار تأیید
                                    @elseif($exchange->status === 'rejected')
                                        رد شده
                                    @elseif($exchange->status === 'suspended')
                                        تعلیق شده
                                    @else
                                        غیرفعال
                                    @endif
                                </span>
                                @if($exchange->is_default)
                                    <span class="status-badge default-badge">پیش‌فرض</span>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div>
                        @if($exchange->is_active)
                            @if(!$exchange->is_default)
                                <form method="POST" action="{{ route('exchanges.switch', $exchange) }}" style="display: inline;">
                                    @csrf
                                    <button type="submit" class="btn">
                                        تنظیم به عنوان پیش‌فرض
                                    </button>
                                </form>
                            @endif
                        @endif
                    </div>
                </div>

                <div class="exchange-details">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 15px;">
                        <div>
                            <strong>کلید API:</strong>
                            <div class="masked-key">{{ $exchange->masked_api_key }}</div>
                        </div>
                        <div>
                            <strong>تاریخ درخواست:</strong>
                            <div>{{ $exchange->activation_requested_at ? $exchange->activation_requested_at->format('Y-m-d H:i') : '-' }}</div>
                        </div>
                    </div>

                    @if($exchange->user_reason)
                        <div style="margin-bottom: 15px;">
                            <strong>دلیل درخواست:</strong>
                            <div style="background: #f8f9fa; padding: 10px; border-radius: 6px; margin-top: 5px;">
                                {{ $exchange->user_reason }}
                            </div>
                        </div>
                    @endif

                    @if($exchange->admin_notes)
                        <div style="margin-bottom: 15px;">
                            <strong>یادداشت مدیر:</strong>
                            <div style="background: #f8f9fa; padding: 10px; border-radius: 6px; margin-top: 5px;">
                                {{ $exchange->admin_notes }}
                            </div>
                        </div>
                    @endif
                    <button onclick="testConnection({{ $exchange->id }})" class="btn " id="test-btn-{{ $exchange->id }}">
                        تست اتصال
                    </button>
                    <div style="text-align: left;">
                        @if($exchange->is_active || $exchange->status === 'rejected')
                            <a href="{{ route('exchanges.edit', $exchange) }}" class="btn btn-warning">
                                ویرایش اطلاعات
                            </a>
                        @endif

                        @if($exchange->status === 'pending')
                            <span style="color: #666; font-style: italic;">در انتظار بررسی مدیر...</span>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    @else
        <div class="empty-state">
            <h3>هیچ صرافی‌ای اضافه نکرده‌اید</h3>
            <p>برای شروع معاملات، ابتدا یک صرافی اضافه کنید</p>
            <a href="{{ route('exchanges.create') }}" class="btn btn-primary">
                افزودن اولین صرافی
            </a>
        </div>
    @endif
</div>

<script>
async function testConnection(exchangeId) {
    const btn = document.getElementById(`test-btn-${exchangeId}`);
    const originalText = btn.textContent;

    btn.textContent = 'در حال تست...';
    btn.disabled = true;

    try {
        const response = await fetch(`/exchanges/${exchangeId}/test-connection`, {
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
            btn.style.color = 'white';
            setTimeout(() => {
                btn.textContent = originalText;
                btn.style.backgroundColor = '';
                btn.style.color = '';
                btn.disabled = false;
            }, 2000);
        } else {
            btn.textContent = '✗ خطا';
            btn.style.backgroundColor = '#dc3545';
            btn.style.color = 'white';
            setTimeout(() => {
                btn.textContent = originalText;
                btn.style.backgroundColor = '';
                btn.style.color = '';
                btn.disabled = false;
            }, 2000);
        }
    } catch (error) {
        btn.textContent = '✗ خطا';
        btn.style.backgroundColor = '#dc3545';
        btn.style.color = 'white';
        setTimeout(() => {
            btn.textContent = originalText;
            btn.style.backgroundColor = '';
            btn.style.color = '';
            btn.disabled = false;
        }, 2000);
    }
}
</script>
@endsection
