@extends('layouts.app')

@section('title', 'همه صرافی‌ها')

@push('styles')
<style>
    .container {
        width: 100%;
        max-width: 1200px;
        margin: auto;
    }
    .admin-header {
        background: #ffffff;
        padding: 20px;
        border-radius: 15px;
        box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        margin-bottom: 20px;
        text-align: center;
    }
    .exchanges-table {
        background: #ffffff;
        border-radius: 15px;
        box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        overflow: hidden;
    }
    table {
        width: 100%;
        border-collapse: collapse;
    }
    th, td {
        padding: 12px;
        text-align: right;
        border-bottom: 1px solid rgba(238, 238, 238, 0.25);
    }
    th {
        background-color: #f8f9fa;
        font-weight: bold;
    }
    .exchange-info {
        display: flex;
        align-items: center;
    }
    .exchange-logo {
        width: 30px;
        height: 30px;
        border-radius: 50%;
        margin-left: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        font-size: 12px;
        color: white;
    }
    .status-badge {
        display: inline-block;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: bold;
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
        margin-right: 5px;
        font-size: 10px;
    }
    .btn {
        display: inline-block;
        padding: 6px 12px;
        margin: 2px;
        text-decoration: none;
        border-radius: 4px;
        font-size: 12px;
        font-weight: bold;
        transition: opacity 0.3s;
        border: none;
        cursor: pointer;
    }
    .btn:hover {
        opacity: 0.8;
    }
    .btn-success {
        background-color: #28a745;
        color: white;
    }
    .btn-warning {
        background-color: #ffc107;
        color: black;
    }
    .btn-danger {
        background-color: #dc3545;
        color: white;
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
    .masked-key {
        font-family: monospace;
        font-size: 10px;
    }
    .notes-cell {
        max-width: 200px;
        word-wrap: break-word;
        font-size: 11px;
    }
    .action-form {
        display: inline;
    }

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

        /* Convert table to card layout on mobile */
        table {
            display: block;
            width: 100%;
            overflow-x: auto;
            white-space: nowrap;
        }

        thead {
            display: none;
        }

        tbody {
            display: block;
        }

        tr {
            display: block;
            background: #fff;
            margin-bottom: 10px;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            white-space: normal;
        }

        td {
            display: block;
            padding: 8px 0;
            border-bottom: 1px solid rgba(238, 238, 238, 0.25);
            text-align: right;
        }

        td:last-child {
            border-bottom: none;
        }

        td:before {
            content: attr(data-label) ": ";
            font-weight: bold;
            display: inline-block;
            margin-left: 10px;
            color: #333;
        }

        .exchange-info {
            flex-direction: column;
            align-items: flex-start;
            text-align: right;
        }

        .exchange-logo {
            width: 25px;
            height: 25px;
            font-size: 10px;
            margin: 0 0 5px 10px;
        }

        .status-badge {
            padding: 3px 6px;
            font-size: 10px;
        }

        .default-badge {
            font-size: 9px;
            padding: 2px 4px;
        }

        .masked-key {
            font-size: 9px;
            word-break: break-all;
        }

        .notes-cell {
            max-width: none;
            font-size: 10px;
        }

        .btn {
            padding: 8px 12px;
            margin: 2px;
            font-size: 11px;
            display: inline-block;
        }

        .alert {
            padding: 10px 12px;
            margin: 10px;
            font-size: 0.9em;
        }

        /* Modal responsiveness */
        #rejectModal > div,
        #deactivateModal > div {
            width: 90%;
            max-width: 350px;
            padding: 20px;
        }

        #rejectModal h3,
        #deactivateModal h3 {
            font-size: 1.1em;
            margin-bottom: 15px;
        }

        #rejectModal textarea,
        #deactivateModal textarea {
            font-size: 14px;
            min-height: 60px;
        }

        #rejectModal button,
        #deactivateModal button {
            padding: 8px 15px;
            font-size: 12px;
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

        tr {
            padding: 10px;
            margin-bottom: 8px;
        }

        td {
            padding: 6px 0;
            font-size: 0.85em;
        }

        .exchange-logo {
            width: 20px;
            height: 20px;
            font-size: 8px;
        }

        .btn {
            padding: 6px 10px;
            font-size: 10px;
            margin: 1px;
        }

        #rejectModal > div,
        #deactivateModal > div {
            width: 95%;
            padding: 15px;
        }
    }
</style>
@endpush

@section('content')
<div class="container">
    <div class="admin-header">
        <h2>مدیریت صرافی‌ها - همه صرافی‌ها</h2>
    </div>

    <div class="admin-nav-links">
        <a href="{{ route('admin.pending-exchanges') }}">درخواست‌های در انتظار</a>
        <a href="{{ route('admin.all-exchanges') }}" class="active">همه صرافی‌ها</a>
        <a href="{{ route('admin.all-users') }}">همه کاربران</a>
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

    <div class="exchanges-table">
        @if($exchanges->count() > 0)
            <table>
                <thead>
                    <tr>
                        <th>صرافی</th>
                        <th>کاربر</th>
                        <th>وضعیت</th>
                        <th>کلید API</th>
                        <th>تاریخ درخواست</th>
                        <th>فعال شده توسط</th>
                        <th>یادداشت مدیر</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($exchanges as $exchange)
                        <tr>
                            <td data-label="صرافی">
                                <div class="exchange-info">
                                    <div class="exchange-logo" style="background-color: {{ $exchange->exchange_color }}">
                                        {{ substr($exchange->exchange_display_name, 0, 2) }}
                                    </div>
                                    <div>
                                        {{ $exchange->exchange_display_name }}
                                        @if($exchange->is_default)
                                            <span class="status-badge default-badge">پیش‌فرض</span>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td data-label="کاربر">
                                @if($exchange->user)
                                    <div>{{ $exchange->user->email }}</div>
                                    <small>({{ $exchange->user->username }})</small>
                                @else
                                    <div style="color: #dc3545;">کاربر حذف شده</div>
                                    <small>(ID: {{ $exchange->user_id }})</small>
                                @endif
                            </td>
                            <td data-label="وضعیت">
                                <span class="status-badge status-{{ $exchange->status === 'approved' && $exchange->is_active ? 'active' : $exchange->status }}">
                                    @if($exchange->status === 'approved' && $exchange->is_active)
                                        فعال
                                    @elseif($exchange->status === 'pending')
                                        در انتظار
                                    @elseif($exchange->status === 'rejected')
                                        رد شده
                                    @elseif($exchange->status === 'suspended')
                                        تعلیق
                                    @else
                                        غیرفعال
                                    @endif
                                </span>
                            </td>
                            <td data-label="کلید API">
                                <div class="masked-key">{{ $exchange->masked_api_key }}</div>
                            </td>
                            <td data-label="تاریخ درخواست">{{ $exchange->activation_requested_at ? $exchange->activation_requested_at->format('Y-m-d') : '-' }}</td>
                            <td data-label="فعال شده توسط">
                                @if($exchange->activatedBy)
                                    {{ $exchange->activatedBy->username }}
                                @else
                                    -
                                @endif
                            </td>
                            <td data-label="یادداشت مدیر" class="notes-cell">{{ $exchange->admin_notes ?: '-' }}</td>
                            <td data-label="عملیات">
                                @if($exchange->status === 'pending')
                                    <form method="POST" action="{{ route('admin.approve-exchange', $exchange) }}" class="action-form">
                                        @csrf
                                        <button type="submit" class="btn btn-success"
                                                onclick="return confirm('آیا مطمئن هستید؟')"
                                                {{ !$exchange->user ? 'disabled title="کاربر حذف شده"' : '' }}>
                                            تأیید
                                        </button>
                                    </form>

                                    <button onclick="showRejectModal({{ $exchange->id }})" class="btn btn-danger"
                                            {{ !$exchange->user ? 'disabled title="کاربر حذف شده"' : '' }}>
                                        رد
                                    </button>

                                    <button type="button" class="btn btn-info" onclick="testConnection({{ $exchange->id }})" id="test-btn-{{ $exchange->id }}">
                                        تست اتصال
                                    </button>
                                @elseif($exchange->is_active)
                                    <button onclick="showDeactivateModal({{ $exchange->id }})" class="btn btn-warning">
                                        غیرفعال‌سازی
                                    </button>
                                @endif

                                @if($exchange->status === 'rejected')
                                    <form method="POST" action="{{ route('admin.approve-exchange', $exchange) }}" class="action-form">
                                        @csrf
                                        <button type="submit" class="btn btn-info"
                                                onclick="return confirm('آیا مطمئن هستید؟')">
                                            فعال‌سازی مجدد
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div style="padding: 20px;">
                {{ $exchanges->links() }}
            </div>
        @else
            <div style="text-align: center; padding: 40px;">
                <h3>صرافی‌ای وجود ندارد</h3>
            </div>
        @endif
    </div>
</div>

<!-- Reject Modal -->
<div id="rejectModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 30px; border-radius: 15px; width: 400px;">
        <h3>رد درخواست</h3>
        <form id="rejectForm" method="POST">
            @csrf
            <div style="margin-bottom: 15px;">
                <label>دلیل رد:</label>
                <textarea name="admin_notes" required style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 6px; resize: vertical; min-height: 80px; box-sizing: border-box;" placeholder="دلیل رد درخواست را بنویسید..."></textarea>
            </div>
            <div style="text-align: left;">
                <button type="button" onclick="hideRejectModal()" style="background: #6c757d; color: white; border: none; padding: 10px 20px; border-radius: 6px; margin-left: 10px;">انصراف</button>
                <button type="submit" style="background: #dc3545; color: white; border: none; padding: 10px 20px; border-radius: 6px;">رد کردن</button>
            </div>
        </form>
    </div>
</div>

<!-- Deactivate Modal -->
<div id="deactivateModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 30px; border-radius: 15px; width: 400px;">
        <h3>غیرفعال‌سازی صرافی</h3>
        <form id="deactivateForm" method="POST">
            @csrf
            <div style="margin-bottom: 15px;">
                <label>دلیل غیرفعال‌سازی:</label>
                <textarea name="admin_notes" required style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 6px; resize: vertical; min-height: 80px; box-sizing: border-box;" placeholder="دلیل غیرفعال‌سازی را بنویسید..."></textarea>
            </div>
            <div style="text-align: left;">
                <button type="button" onclick="hideDeactivateModal()" style="background: #6c757d; color: white; border: none; padding: 10px 20px; border-radius: 6px; margin-left: 10px;">انصراف</button>
                <button type="submit" style="background: #ffc107; color: white; border: none; padding: 10px 20px; border-radius: 6px;">غیرفعال کردن</button>
            </div>
        </form>
    </div>
</div>

<script>
// CSRF token for AJAX requests
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

function testConnection(exchangeId) {
    const button = document.getElementById(`test-btn-${exchangeId}`);

    if (!button) return;

    // Show loading state
    button.disabled = true;
    const originalText = button.textContent;
    button.textContent = 'در حال تست...';

    // Make AJAX request
    fetch(`/admin/exchanges/${exchangeId}/test`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            'Accept': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        button.disabled = false;
        button.textContent = originalText;

        if (data.success) {
            alert(`نتیجه تست: ${data.message}`);
        } else {
            alert(`خطا: ${data.message}`);
        }
    })
    .catch(error => {
        button.disabled = false;
        button.textContent = originalText;
        alert('خطا در ارتباط: لطفاً دوباره تلاش کنید');
        console.error('Test connection error:', error);
    });
}

function showRejectModal(exchangeId) {
    document.getElementById('rejectForm').action = `/admin/exchanges/${exchangeId}/reject`;
    document.getElementById('rejectModal').style.display = 'block';
}

function hideRejectModal() {
    document.getElementById('rejectModal').style.display = 'none';
}

function showDeactivateModal(exchangeId) {
    document.getElementById('deactivateForm').action = `/admin/exchanges/${exchangeId}/deactivate`;
    document.getElementById('deactivateModal').style.display = 'block';
}

function hideDeactivateModal() {
    document.getElementById('deactivateModal').style.display = 'none';
}

// Close modals when clicking outside
window.onclick = function(event) {
    const rejectModal = document.getElementById('rejectModal');
    const deactivateModal = document.getElementById('deactivateModal');
    if (event.target === rejectModal) {
        hideRejectModal();
    }
    if (event.target === deactivateModal) {
        hideDeactivateModal();
    }
}
</script>
@endsection
