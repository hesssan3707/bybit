@extends('layouts.app')

@section('body-class', 'admin-page')

@section('title', 'درخواست‌های فعال‌سازی صرافی')

@push('styles')
<style>
    * {
        box-sizing: border-box;
    }
    
    body {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        min-height: 100vh;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }
    
    .admin-container {
        max-width: 1400px;
        margin: 0 auto;
        padding: 20px;
    }
    
    .admin-header {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(10px);
        border-radius: 20px;
        padding: 30px;
        margin-bottom: 30px;
        text-align: center;
        border: 1px solid rgba(255, 255, 255, 0.2);
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
    }
    
    .admin-header h1 {
        margin: 0;
        color: #2c3e50;
        font-size: 2.5rem;
        font-weight: 700;
        background: linear-gradient(135deg, #667eea, #764ba2);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }
    
    .admin-nav {
        display: flex;
        justify-content: center;
        gap: 15px;
        margin-top: 20px;
        flex-wrap: wrap;
    }
    
    .nav-btn {
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
        text-decoration: none;
        padding: 12px 24px;
        border-radius: 50px;
        font-weight: 600;
        transition: all 0.3s ease;
        border: none;
        cursor: pointer;
        box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
    }
    
    .nav-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        color: white;
        text-decoration: none;
    }
    
    .nav-btn.active {
        background: linear-gradient(135deg, #764ba2, #667eea);
        box-shadow: 0 6px 20px rgba(118, 75, 162, 0.4);
    }
    
    .content-card {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(10px);
        border-radius: 20px;
        padding: 30px;
        border: 1px solid rgba(255, 255, 255, 0.2);
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
    }
    
    .table-container {
        overflow-x: auto;
        border-radius: 15px;
        background: white;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    }
    .exchange-row {
        border-bottom: 1px solid #eee;
        padding: 20px;
        display: grid;
        grid-template-columns: auto 1fr auto;
        gap: 20px;
        align-items: center;
    }
    .exchange-row:last-child {
        border-bottom: none;
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
    .exchange-details {
        flex: 1;
    }
    .user-info {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 15px;
    }
    .masked-key {
        font-family: monospace;
        background: #e9ecef;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 12px;
        margin-top: 5px;
    }
    .btn {
        display: inline-block;
        padding: 10px 20px;
        margin: 3px;
        text-decoration: none;
        border-radius: 25px;
        font-size: 0.9rem;
        font-weight: 600;
        transition: all 0.3s ease;
        border: none;
        cursor: pointer;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    }
    .btn:hover {
        transform: translateY(-2px);
        text-decoration: none;
    }
    .btn-success {
        background: linear-gradient(135deg, #28a745, #20c997);
        color: white;
        border: 1px solid rgba(255, 255, 255, 0.2);
    }
    .btn-success:hover {
        background: linear-gradient(135deg, #20c997, #28a745);
        box-shadow: 0 8px 25px rgba(40, 167, 69, 0.3);
        color: white;
    }
    .btn-danger {
        background: linear-gradient(135deg, #dc3545, #e74c3c);
        color: white;
        border: 1px solid rgba(255, 255, 255, 0.2);
    }
    .btn-danger:hover {
        background: linear-gradient(135deg, #e74c3c, #dc3545);
        box-shadow: 0 8px 25px rgba(220, 53, 69, 0.3);
        color: white;
    }
    .btn-primary {
        background: linear-gradient(135deg, var(--primary-color), #764ba2);
        color: white;
        border: 1px solid rgba(255, 255, 255, 0.2);
    }
    .btn-primary:hover {
        background: linear-gradient(135deg, #764ba2, var(--primary-color));
        box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
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
    .admin-nav-links {
        margin-bottom: 20px;
        text-align: center;
    }
    .admin-nav-links a {
        display: inline-block;
        padding: 10px 20px;
        margin: 0 5px;
        background-color: var(--primary-color);
        color: white;
        text-decoration: none;
        border-radius: 8px;
        font-weight: bold;
    }
    .admin-nav-links a:hover {
        opacity: 0.8;
    }
    .admin-nav-links a.active {
        background-color: var(--primary-hover, #5a32a3) !important;
        opacity: 1;
    }
    .admin-nav-links a.active {
        background-color: var(--primary-hover);
    }
    .empty-state {
        text-align: center;
        padding: 40px 20px;
        color: #666;
    }
    .approval-form {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 8px;
        margin-top: 15px;
    }
    .approval-form textarea {
        width: 100%;
        padding: 10px;
        border: 1px solid #ccc;
        border-radius: 6px;
        resize: vertical;
        min-height: 80px;
        box-sizing: border-box;
    }
    .approval-actions {
        margin-top: 10px;
        display: flex;
        gap: 10px;
    }
    .test-result {
        padding: 10px;
        border-radius: 6px;
        font-size: 14px;
    }
    .test-result.success {
        background-color: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }
    .test-result.warning {
        background-color: #fff3cd;
        color: #856404;
        border: 1px solid #ffeaa7;
    }
    .test-result.error {
        background-color: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }
    .test-result.loading {
        background-color: #e3f2fd;
        color: #0277bd;
        border: 1px solid #bbdefb;
    }
    .validation-details {
        margin-top: 10px;
    }
    .validation-item {
        display: flex;
        justify-content: space-between;
        padding: 5px 0;
        border-bottom: 1px solid #eee;
    }
    .validation-item:last-child {
        border-bottom: none;
    }
    .status-icon {
        font-weight: bold;
    }
    .status-allowed { color: #28a745; }
    .status-denied { color: #dc3545; }
    .status-blocked { color: #dc3545; }
    .status-not-supported { color: #6c757d; }
    
    /* Mobile Responsive Styles */
    @media (max-width: 768px) {
        .container {
            padding: 0;
            margin: 0 auto;
            width: 100%;
        }
        
        .admin-header {
            padding: 15px;
            margin: 10px;
            width: calc(100% - 20px);
            box-sizing: border-box;
        }
        
        .admin-header h2 {
            font-size: 1.3em;
            margin: 0;
        }
        
        .admin-nav-links {
            margin: 10px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }
        
        .admin-nav-links a {
            padding: 8px 12px;
            margin: 0;
            font-size: 0.85em;
            text-align: center;
        }
        
        .exchanges-table {
            margin: 10px;
            width: calc(100% - 20px);
            box-sizing: border-box;
            border-radius: 10px;
        }
        
        .exchange-row {
            display: block;
            padding: 15px;
            gap: 15px;
        }
        
        .exchange-info {
            flex-direction: column;
            align-items: center;
            text-align: center;
            margin-bottom: 15px;
        }
        
        .exchange-logo {
            width: 40px;
            height: 40px;
            font-size: 16px;
            margin: 0 0 10px 0;
        }
        
        .exchange-details {
            width: 100%;
        }
        
        .user-info {
            padding: 12px;
            font-size: 0.9em;
        }
        
        .masked-key {
            font-size: 10px;
            word-break: break-all;
        }
        
        .approval-form {
            padding: 12px;
        }
        
        .approval-form textarea {
            font-size: 14px;
            min-height: 60px;
        }
        
        .btn {
            padding: 8px 12px;
            font-size: 12px;
        }
        
        .test-connection-section {
            padding: 8px;
        }
        
        .test-result {
            font-size: 12px;
            padding: 8px;
        }
        
        .validation-item {
            flex-direction: column;
            align-items: flex-start;
            padding: 8px 0;
        }
        
        .empty-state {
            padding: 30px 15px;
        }
        
        .empty-state h3 {
            font-size: 1.1em;
        }
        
        .empty-state p {
            font-size: 0.9em;
        }
    }
    
    /* Extra small screens */
    @media (max-width: 480px) {
        .admin-header {
            padding: 10px;
            margin: 5px;
            width: calc(100% - 10px);
        }
        
        .admin-header h2 {
            font-size: 1.1em;
        }
        
        .admin-nav-links {
            margin: 5px;
            grid-template-columns: 1fr;
            gap: 5px;
        }
        
        .admin-nav-links a {
            padding: 6px 10px;
            font-size: 0.8em;
        }
        
        .exchanges-table {
            margin: 5px;
            width: calc(100% - 10px);
        }
        
        .exchange-row {
            padding: 10px;
        }
        
        .exchange-logo {
            width: 35px;
            height: 35px;
            font-size: 14px;
        }
        
        .user-info {
            padding: 10px;
            font-size: 0.85em;
        }
        
        .approval-form {
            padding: 10px;
        }
        
        .btn {
            padding: 6px 10px;
            font-size: 11px;
        }
        
        .test-result {
            font-size: 11px;
            padding: 6px;
        }
    }
</style>
@endpush

@section('content')
<div class="admin-container">
    <div class="admin-header">
        <h1>پنل مدیریت</h1>
        <nav class="admin-nav">
            <a href="{{ route('admin.pending-exchanges') }}" class="nav-btn active">درخواست‌های در انتظار</a>
            <a href="{{ route('admin.all-exchanges') }}" class="nav-btn">همه صرافی‌ها</a>
            <a href="{{ route('admin.all-users') }}" class="nav-btn">همه کاربران</a>
            <a href="{{ route('admin.pending-users') }}" class="nav-btn">کاربران در انتظار</a>
        </nav>
    </div>

    @if($pendingExchanges->count() > 0)
        <div class="content-card" style="margin-bottom: 20px;">
            <div style="text-align: center; padding: 20px;">
                <h3 style="margin: 0; color: #2c3e50;">درخواست‌های در انتظار تأیید</h3>
                <p style="margin: 10px 0 0 0; color: #6c757d;">{{ $pendingExchanges->count() }} درخواست</p>
            </div>
        </div>
    @endif

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

    <div class="content-card">
        @if($pendingExchanges->count() > 0)
            @foreach($pendingExchanges as $exchange)
                <div class="exchange-row">
                    <div class="exchange-info">
                        <div class="exchange-logo" style="background-color: {{ $exchange->exchange_color }}">
                            {{ substr($exchange->exchange_display_name, 0, 2) }}
                        </div>
                        <div>
                            <h4 style="margin: 0;">{{ $exchange->exchange_display_name }}</h4>
                            <small style="color: #666;">{{ $exchange->created_at->format('Y-m-d H:i') }}</small>
                        </div>
                    </div>
                    
                    <div class="exchange-details">
                        <div class="user-info">
                            @if($exchange->user)
                                <strong>کاربر:</strong> {{ $exchange->user->email }} ({{ $exchange->user->username }})<br>
                            @else
                                <strong>کاربر:</strong> <span style="color: #dc3545;">کاربر حذف شده (ID: {{ $exchange->user_id }})</span><br>
                            @endif
                            <strong>تاریخ درخواست:</strong> {{ $exchange->activation_requested_at->format('Y-m-d H:i') }}<br>
                            <strong>کلید API واقعی:</strong>
                            <div class="masked-key">{{ $exchange->masked_api_key }}</div>
                            @if($exchange->hasDemoCredentials())
                                <strong>کلید API دمو:</strong>
                                <div class="masked-key">{{ $exchange->masked_demo_api_key }}</div>
                            @endif
                        </div>
                        
                        @if($exchange->user_reason)
                            <div style="margin-bottom: 15px;">
                                <strong>دلیل کاربر:</strong>
                                <div style="background: #e3f2fd; padding: 10px; border-radius: 6px; margin-top: 5px;">
                                    {{ $exchange->user_reason }}
                                </div>
                            </div>
                        @endif
                        
                        <div class="approval-form">
                            <!-- Test Connection Section -->
                            <div class="test-connection-section" style="margin-bottom: 15px; padding: 10px; background: #f8f9fa; border-radius: 6px;">
                                <button type="button" class="btn btn-info" onclick="testRealConnection({{ $exchange->id }})" id="test-real-btn-{{ $exchange->id }}">
                                    تست اتصال حساب واقعی
                                </button>
                                @if($exchange->hasDemoCredentials())
                                    <button type="button" class="btn btn-info" onclick="testDemoConnection({{ $exchange->id }})" id="test-demo-btn-{{ $exchange->id }}" style="margin-right: 10px;">
                                        تست اتصال حساب دمو
                                    </button>
                                @endif
                                <div id="test-real-result-{{ $exchange->id }}" class="test-result" style="margin-top: 10px; display: none;"></div>
                                @if($exchange->hasDemoCredentials())
                                    <div id="test-demo-result-{{ $exchange->id }}" class="test-result" style="margin-top: 10px; display: none;"></div>
                                @endif
                            </div>
                            
                            <form id="approve-form-{{ $exchange->id }}" method="POST" action="{{ route('admin.approve-exchange', $exchange) }}" style="display: inline;">
                                @csrf
                                <textarea name="admin_notes" placeholder="یادداشت تأیید (اختیاری)..."></textarea>
                                <div class="approval-actions">
                                    <button type="button" class="btn btn-success" 
                                            onclick="confirmApproveExchange({{ $exchange->id }})">
                                        ✓ تأیید
                                    </button>
                                </div>
                            </form>
                            
                            <form id="reject-form-{{ $exchange->id }}" method="POST" action="{{ route('admin.reject-exchange', $exchange) }}" style="margin-top: 10px;">
                                @csrf
                                <textarea name="admin_notes" placeholder="دلیل رد (الزامی)..." required></textarea>
                                <div class="approval-actions">
                                    <button type="button" class="btn btn-danger" 
                                            onclick="confirmRejectExchange({{ $exchange->id }})">
                                        ✗ رد
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            @endforeach
            
            <div style="padding: 20px;">
                {{ $pendingExchanges->links() }}
            </div>
        @else
            <div class="empty-state">
                <h3>درخواست صرافی‌ای در انتظار تأیید وجود ندارد</h3>
                <p>همه درخواست‌های صرافی بررسی شده‌اند</p>
            </div>
        @endif
    </div>
</div>

<script>
// CSRF token for AJAX requests
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

function testRealConnection(exchangeId) {
    const button = document.getElementById(`test-real-btn-${exchangeId}`);
    const resultDiv = document.getElementById(`test-real-result-${exchangeId}`);
    
    // Show loading state
    button.disabled = true;
    button.textContent = 'در حال تست...';
    resultDiv.style.display = 'block';
    resultDiv.className = 'test-result loading';
    resultDiv.innerHTML = 'در حال بررسی اتصال و اعتبارسنجی کلید API واقعی...';
    
    // Make AJAX request
    fetch(`/admin/exchanges/${exchangeId}/test`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            'Accept': 'application/json'
        },
        body: JSON.stringify({ test_type: 'real' })
    })
    .then(response => response.json())
    .then(data => {
        button.disabled = false;
        button.textContent = 'تست اتصال حساب واقعی';
        
        if (data.success) {
            // Determine result class based on recommendation
            let resultClass = 'success';
            if (data.recommendation === 'reject') {
                resultClass = 'error';
            } else if (data.recommendation === 'approve_with_warning') {
                resultClass = 'warning';
            }
            
            resultDiv.className = `test-result ${resultClass}`;
            
            let html = `<div><strong>${data.message}</strong></div>`;
            
            if (data.details) {
                html += '<div class="validation-details">';
                
                if (data.details.ip) {
                    const ipIcon = data.details.ip.status === 'allowed' ? '✓' : '✗';
                    const ipClass = `status-${data.details.ip.status}`;
                    html += `<div class="validation-item">
                        <span>دسترسی IP:</span>
                        <span class="status-icon ${ipClass}">${ipIcon} ${data.details.ip.message}</span>
                    </div>`;
                }
                
                if (data.details.spot) {
                    const spotIcon = data.details.spot.status === 'allowed' ? '✓' : (data.details.spot.status === 'not_supported' ? '!' : '✗');
                    const spotClass = `status-${data.details.spot.status}`;
                    html += `<div class="validation-item">
                        <span>معاملات اسپات:</span>
                        <span class="status-icon ${spotClass}">${spotIcon} ${data.details.spot.message}</span>
                    </div>`;
                }
                
                if (data.details.futures) {
                    const futuresIcon = data.details.futures.status === 'allowed' ? '✓' : (data.details.futures.status === 'not_supported' ? '!' : '✗');
                    const futuresClass = `status-${data.details.futures.status}`;
                    html += `<div class="validation-item">
                        <span>معاملات آتی:</span>
                        <span class="status-icon ${futuresClass}">${futuresIcon} ${data.details.futures.message}</span>
                    </div>`;
                }
                
                html += '</div>';
            }
            
            resultDiv.innerHTML = html;
        } else {
            resultDiv.className = 'test-result error';
            resultDiv.innerHTML = `<div><strong>خطا:</strong> ${data.message}</div>`;
        }
    })
    .catch(error => {
        button.disabled = false;
        button.textContent = 'تست اتصال حساب واقعی';
        resultDiv.className = 'test-result error';
        resultDiv.innerHTML = `<div><strong>خطا در ارتباط:</strong> لطفاً دوباره تلاش کنید</div>`;
        console.error('Test real connection error:', error);
    });
}

function testDemoConnection(exchangeId) {
    const button = document.getElementById(`test-demo-btn-${exchangeId}`);
    const resultDiv = document.getElementById(`test-demo-result-${exchangeId}`);
    
    // Show loading state
    button.disabled = true;
    button.textContent = 'در حال تست...';
    resultDiv.style.display = 'block';
    resultDiv.className = 'test-result loading';
    resultDiv.innerHTML = 'در حال بررسی اتصال و اعتبارسنجی کلید API دمو...';
    
    // Make AJAX request
    fetch(`/admin/exchanges/${exchangeId}/test`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            'Accept': 'application/json'
        },
        body: JSON.stringify({ test_type: 'demo' })
    })
    .then(response => response.json())
    .then(data => {
        button.disabled = false;
        button.textContent = 'تست اتصال حساب دمو';
        
        if (data.success) {
            // Determine result class based on recommendation
            let resultClass = 'success';
            if (data.recommendation === 'reject') {
                resultClass = 'error';
            } else if (data.recommendation === 'approve_with_warning') {
                resultClass = 'warning';
            }
            
            resultDiv.className = `test-result ${resultClass}`;
            
            let html = `<div><strong>${data.message}</strong></div>`;
            
            if (data.details) {
                html += '<div class="validation-details">';
                
                if (data.details.ip) {
                    const ipIcon = data.details.ip.status === 'allowed' ? '✓' : '✗';
                    const ipClass = `status-${data.details.ip.status}`;
                    html += `<div class="validation-item">
                        <span>دسترسی IP:</span>
                        <span class="status-icon ${ipClass}">${ipIcon} ${data.details.ip.message}</span>
                    </div>`;
                }
                
                if (data.details.spot) {
                    const spotIcon = data.details.spot.status === 'allowed' ? '✓' : (data.details.spot.status === 'not_supported' ? '!' : '✗');
                    const spotClass = `status-${data.details.spot.status}`;
                    html += `<div class="validation-item">
                        <span>معاملات اسپات:</span>
                        <span class="status-icon ${spotClass}">${spotIcon} ${data.details.spot.message}</span>
                    </div>`;
                }
                
                if (data.details.futures) {
                    const futuresIcon = data.details.futures.status === 'allowed' ? '✓' : (data.details.futures.status === 'not_supported' ? '!' : '✗');
                    const futuresClass = `status-${data.details.futures.status}`;
                    html += `<div class="validation-item">
                        <span>معاملات آتی:</span>
                        <span class="status-icon ${futuresClass}">${futuresIcon} ${data.details.futures.message}</span>
                    </div>`;
                }
                
                html += '</div>';
            }
            
            resultDiv.innerHTML = html;
        } else {
            resultDiv.className = 'test-result error';
            resultDiv.innerHTML = `<div><strong>خطا:</strong> ${data.message}</div>`;
        }
    })
    .catch(error => {
        button.disabled = false;
        button.textContent = 'تست اتصال حساب دمو';
        resultDiv.className = 'test-result error';
        resultDiv.innerHTML = `<div><strong>خطا در ارتباط:</strong> لطفاً دوباره تلاش کنید</div>`;
        console.error('Test demo connection error:', error);
    });
}

function confirmApproveExchange(exchangeId) {
    modernConfirm(
        'تأیید درخواست صرافی',
        'آیا مطمئن هستید که می‌خواهید این درخواست صرافی را تأیید کنید؟',
        function() {
            document.getElementById('approve-form-' + exchangeId).submit();
        }
    );
}

function confirmRejectExchange(exchangeId) {
    modernConfirm(
        'رد درخواست صرافی',
        'آیا مطمئن هستید که می‌خواهید این درخواست صرافی را رد کنید؟',
        function() {
            document.getElementById('reject-form-' + exchangeId).submit();
        }
    );
}
</script>

@include('partials.alert-modal')
@endsection