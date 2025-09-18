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
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border-radius: 20px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        margin-bottom: 25px;
        overflow: hidden;
        border: 1px solid rgba(255, 255, 255, 0.2);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    .exchange-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 40px rgba(0,0,0,0.3);
    }
    .exchange-header {
        padding: 25px;
        color: white;
        position: relative;
        overflow: hidden;
    }
    .exchange-header::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: linear-gradient(45deg, transparent 30%, rgba(255,255,255,0.1) 50%, transparent 70%);
        animation: shimmer 3s infinite;
    }
    @keyframes shimmer {
        0% { transform: translateX(-100%); }
        100% { transform: translateX(100%); }
    }
    .exchange-info {
        display: flex;
        align-items: center;
        margin-bottom: 20px;
        position: relative;
        z-index: 1;
    }
    .exchange-logo {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        background: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        color: var(--exchange-color, #007bff);
        box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        border: 1px solid rgba(255,255,255,0.3);
    }
    .exchange-title {
        flex: 1;
        margin-right:20px
    }
    .exchange-title h3 {
        margin: 0 0 8px 0;
        font-size: 24px;
        font-weight: 700;
        text-shadow: 0 2px 4px rgba(0,0,0,0.3);
    }
    .exchange-details {
        padding: 25px;
        color: #333;
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
    .exchange-actions {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 15px;
        padding-top: 20px;
        border-top: 1px solid rgba(0,0,0,0.1);
    }
    .action-buttons {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }
    .status-badges {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 8px;
    }
    .status-badge {
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        display: inline-flex;
        align-items: center;
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.3);
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    .status-active { 
        background: linear-gradient(135deg, rgba(40, 167, 69, 0.9), rgba(40, 167, 69, 0.7)); 
        color: white; 
    }
    .status-pending { 
        background: linear-gradient(135deg, rgba(255, 152, 0, 0.9), rgba(255, 152, 0, 0.7)); 
        color: white; 
    }
    .status-rejected { 
        background: linear-gradient(135deg, rgba(244, 67, 54, 0.9), rgba(244, 67, 54, 0.7)); 
        color: white; 
    }
    .status-suspended {
        background: linear-gradient(135deg, rgba(108, 117, 125, 0.9), rgba(108, 117, 125, 0.7)); 
        color: white; 
    }
    .default-badge {
        background: linear-gradient(135deg, rgba(23, 162, 184, 0.9), rgba(23, 162, 184, 0.7)); 
        color: white;
        margin-right: 10px;
    }
    .btn {
        padding: 10px 20px;
        border: none;
        border-radius: 12px;
        cursor: pointer;
        font-size: 13px;
        font-weight: 600;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: all 0.3s ease;
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.2);
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        position: relative;
        overflow: hidden;
        margin: 4px;
    }
    .btn::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
        transition: left 0.5s;
    }
    .btn:hover::before {
        left: 100%;
    }
    .btn-primary { 
        background: linear-gradient(135deg, #007bff, #0056b3); 
        color: white; 
    }
    .btn-success { 
        background: linear-gradient(135deg, #28a745, #1e7e34); 
        color: white; 
    }
    .btn-info { 
        background: linear-gradient(135deg, #17a2b8, #117a8b); 
        color: white; 
    }
    .btn-warning { 
        background: linear-gradient(135deg, #ffc107, #e0a800); 
        color: #212529; 
    }
    .btn-danger { 
        background: linear-gradient(135deg, #dc3545, #c82333); 
        color: white; 
    }
    .btn:hover { 
        transform: translateY(-3px); 
        box-shadow: 0 8px 25px rgba(0,0,0,0.2); 
    }
    .btn:active {
        transform: translateY(-1px);
        box-shadow: 0 4px 15px rgba(0,0,0,0.2);
    }
    .btn i {
        font-size: 12px;
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
    
    /* Mode Switch Styles */
    .switch {
        position: relative;
        display: inline-block;
        width: 98px;
        height: 46px;
    }

    .switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }

    .slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: #ccc;
        transition: .4s;
        border-radius: 40px;
    }

    .slider:before {
        position: absolute;
        content: "";
        height: 40px;
        width: 40px;
        left: 3px;
        bottom: 3px;
        background-color: white;
        transition: .4s;
        border-radius: 50%;
    }

    input:checked + .slider {
        background-color: #2196F3;
    }

    input:checked + .slider:before {
        transform: translateX(52px);
    }

    .slider.round {
        border-radius: 36px;
    }

    .slider.round:before {
        border-radius: 50%;
    }

    .switch-labels {
        position: absolute;
        top: 49%;
        left: 0;
        right: 0;
        transform: translateY(-50%);
        display: flex;
        justify-content: space-between;
        padding: 0 10px;
        color: white;
        font-weight: bold;
        pointer-events: none;
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
                        <div class="exchange-logo">
                            <img src="{{ asset('public/logos/' . strtolower($exchange->exchange_display_name) . '-logo.png') }}" alt="{{ subStr($exchange->exchange_display_name , 0 , 2) }}" class="exchange-logo" style="background-color: {{ $exchange->exchange_color }};">
                        </div>
                        <div class="exchange-title">
                            <h3>{{ $exchange->exchange_display_name }}</h3>
                            <div class="status-badges">
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
                                @if($exchange->is_active && ($exchange->demo_api_key || $exchange->api_key))
                                    <div style="margin-top: 10px;">
                                        <div class="mode-switch">
                                            <label class="switch">
                                                <input type="checkbox" 
                                                       id="mode-switch-{{ $exchange->id }}" 
                                                       {{ $exchange->is_demo_active ? 'checked' : '' }}
                                                       onchange="switchMode({{ $exchange->id }}, this)">
                                                <span class="slider round"></span>
                                                <div class="switch-labels">
                                                    <span>واقعی</span>
                                                    <span>دمو</span>
                                                </div>
                                            </label>
                                        </div>
                                    </div>
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
                    <div class="exchange-details-grid">
                        <div class="detail-item">
                            <div class="detail-label">کلید API</div>
                            <div class="detail-value">{{ $exchange->masked_api_key }}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">تاریخ درخواست</div>
                            <div class="detail-value">{{ $exchange->activation_requested_at ? $exchange->activation_requested_at->format('Y-m-d H:i') : '-' }}</div>
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
                    <div class="exchange-actions">
                        <div class="action-buttons">
                            @if($exchange->is_active || $exchange->status === 'rejected')
                                <a href="{{ route('exchanges.edit', $exchange) }}" class="btn btn-warning">
                                    <i class="fas fa-edit"></i> ویرایش اطلاعات
                                </a>
                            @endif
                            
                            @if(!empty($exchange->api_key) && !empty($exchange->api_secret))
                                <button onclick="testRealConnection({{ $exchange->id }})" class="btn btn-success" id="test-real-btn-{{ $exchange->id }}">
                                    <i class="fas fa-plug"></i> تست اتصال حساب واقعی
                                </button>
                            @endif
                            
                            @if(!empty($exchange->demo_api_key) && !empty($exchange->demo_api_secret))
                                <button onclick="testDemoConnection({{ $exchange->id }})" class="btn btn-info" id="test-demo-btn-{{ $exchange->id }}">
                                    <i class="fas fa-vial"></i> تست اتصال حساب دمو
                                </button>
                            @endif
                        </div>
                        
                        @if($exchange->status === 'pending')
                            <span style="color: #aea6a6; font-style: italic;">در انتظار بررسی مدیر...</span>
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
async function switchMode(exchangeId, checkbox) {
            const originalState = checkbox.checked;
            
            try {
                const response = await fetch(`/exchanges/${exchangeId}/switch-mode`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        is_demo_mode: checkbox.checked
                    })
                });

                const data = await response.json();
                
                if (data.success) {
                    // Update checkbox to reflect the actual database state
                    checkbox.checked = data.is_demo_mode;
                    // Success is silent - no modal shown
                } else {
                    // Revert checkbox state if the switch failed
                    checkbox.checked = originalState;
                    // Show error modal with server message
                    modernAlert(
                        data.message || 'خطا در تغییر حالت صرافی',
                        'error',
                        'خطا در تغییر حالت'
                    );
                }
            } catch (error) {
                console.error('Error switching mode:', error);
                // Revert checkbox state on error
                checkbox.checked = originalState;
                // Show error modal for network/connection errors
                modernAlert(
                    'خطا در برقراری ارتباط با سرور. لطفاً دوباره تلاش کنید.',
                    'error',
                    'خطا در اتصال'
                );
            }
        }

async function testRealConnection(exchangeId) {
    const btn = document.getElementById(`test-real-btn-${exchangeId}`);
    const originalText = btn.textContent;

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
            modernAlert(
                data.message || 'تست اتصال حساب واقعی ناموفق بود. لطفاً کلیدهای API و تنظیمات شبکه خود را بررسی کنید.',
                'error',
                'خطا در تست اتصال واقعی'
            );
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
        modernAlert('خطا در تست اتصال حساب واقعی', 'error', 'خطا در تست اتصال');
    }
}

async function testDemoConnection(exchangeId) {
    const btn = document.getElementById(`test-demo-btn-${exchangeId}`);
    const originalText = btn.textContent;

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
            modernAlert(
                data.message || 'تست اتصال حساب دمو ناموفق بود. لطفاً کلیدهای API دمو و تنظیمات شبکه خود را بررسی کنید.',
                'error',
                'خطا در تست اتصال دمو'
            );
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
        modernAlert('خطا در تست اتصال حساب دمو', 'error', 'خطا در تست اتصال');
    }
}
</script>
@endsection
