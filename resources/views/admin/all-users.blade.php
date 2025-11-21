@extends('layouts.app')

@section('body-class', 'admin-page')

@section('title', 'همه کاربران')

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
    table {
        width: 100%;
        border-collapse: collapse;
        background: white;
    }
    
    th {
        background: linear-gradient(135deg, #f8f9fa, #e9ecef);
        color: #495057;
        font-weight: 700;
        padding: 20px 15px;
        text-align: right;
        border: none;
        font-size: 0.9rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    td {
        padding: 20px 15px;
        border-bottom: 1px solid #f1f3f4;
        color: #495057;
        vertical-align: middle;
        text-align: right;
    }
    
    tr:hover {
        background: rgba(102, 126, 234, 0.05);
    }
    
    .btn {
        display: inline-block;
        padding: 10px 20px;
        margin: 5px;
        text-decoration: none;
        border-radius: 25px;
        font-size: 0.85rem;
        font-weight: 600;
        border: none;
        cursor: pointer;
        transition: all 0.3s ease;
        text-align: center;
    }
    
    .btn-success {
        background: linear-gradient(135deg, #28a745, #20c997);
        color: white;
        box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
    }
    
    .btn-success:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(40, 167, 69, 0.4);
        color: white;
        text-decoration: none;
    }
    
    .btn-warning {
        background: linear-gradient(135deg, #ffc107, #ffb300);
        color: #212529;
        box-shadow: 0 4px 15px rgba(255, 193, 7, 0.3);
    }
    
    .btn-warning:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(255, 193, 7, 0.4);
        color: #212529;
        text-decoration: none;
    }
    
    .btn-danger {
        background: linear-gradient(135deg, #dc3545, #e74c3c);
        color: white;
        box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3);
    }
    
    .btn-danger:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(220, 53, 69, 0.4);
        color: white;
        text-decoration: none;
    }
    
    .btn-primary {
        background: linear-gradient(135deg, #007bff, #0056b3);
        color: white;
        box-shadow: 0 4px 15px rgba(0, 123, 255, 0.3);
    }
    
    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(0, 123, 255, 0.4);
        color: white;
        text-decoration: none;
    }
    
    .alert {
        padding: 20px;
        margin-bottom: 25px;
        border: none;
        border-radius: 15px;
        font-weight: 500;
        backdrop-filter: blur(10px);
        text-align: center;
    }
    
    .alert-success {
        color: #155724;
        background: rgba(212, 237, 218, 0.9);
        border-left: 4px solid #28a745;
    }
    
    .alert-error {
        color: #721c24;
        background: rgba(248, 215, 218, 0.9);
        border-left: 4px solid #dc3545;
    }
    
    .status-badge {
        display: inline-block;
        padding: 8px 16px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .status-active {
        background: linear-gradient(135deg, #d1e7dd, #c3e6cb);
        color: #0f5132;
    }
    
    .status-inactive {
        background: linear-gradient(135deg, #f8d7da, #fab1a0);
        color: #842029;
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

        .users-table {
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
            border-bottom: 1px solid #eee;
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

        .status-badge {
            padding: 3px 6px;
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

        .users-table {
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

        .btn {
            padding: 6px 10px;
            font-size: 10px;
            margin: 1px;
        }
    }
</style>
@endpush

@section('content')
<div class="admin-container">
    <div class="admin-header">
        <div class="admin-nav">
            <a href="{{ route('admin.pending-users') }}" class="nav-btn">کاربران در انتظار تأیید</a>
            <a href="{{ route('admin.all-users') }}" class="nav-btn">همه کاربران</a>
            <a href="{{ route('admin.pending-exchanges') }}" class="nav-btn">درخواست‌های فعال‌سازی صرافی</a>
            <a href="{{ route('admin.all-exchanges') }}" class="nav-btn">همه صرافی‌ها</a>
            <a href="{{ route('admin.tickets') }}" class="nav-btn active">تیکت‌ها</a>
            <a href="{{ route('admin.company-requests.pending') }}" class="nav-btn">در انتظار</a>
            <a href="{{ route('admin.company-requests.all') }}" class="nav-btn active">همه درخواست‌ها</a>
        </div>
    </div>

    @if(isset($users) && $users->count() > 0)
    <div class="stats-card">
        <div class="stats-number">{{ $users->count() }}</div>
        <div class="stats-label">کل کاربران</div>
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
        @if($users->count() > 0)
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>نام کاربری</th>
                            <th>ایمیل</th>
                            <th>وضعیت</th>
                            <th>تاریخ ثبت‌نام</th>
                            <th>فعال‌شده در</th>
                            <th>فعال‌شده توسط</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($users as $user)
                        <tr>
                            <td data-label="نام کاربری">{{ $user->username }}</td>
                            <td data-label="ایمیل">{{ $user->email }}</td>
                            <td data-label="وضعیت">
                                <span class="status-badge {{ $user->is_active ? 'status-active' : 'status-inactive' }}">
                                    {{ $user->is_active ? 'فعال' : 'غیرفعال' }}
                                </span>
                            </td>
                            <td data-label="تاریخ ثبت‌نام">{{ $user->created_at->format('Y-m-d H:i') }}</td>
                            <td data-label="فعال‌شده در">{{ $user->activated_at ? $user->activated_at->format('Y-m-d H:i') : '-' }}</td>
                            <td data-label="فعال‌شده توسط">{{ $user->activatedBy ? $user->activatedBy->username : '-' }}</td>
                            <td data-label="عملیات">
                                @if($user->is_active)
                                    @if($user->id !== auth()->id())
                                        <form method="POST" action="{{ route('admin.deactivate-user', $user) }}" style="display: inline;" id="deactivate-form-{{ $user->id }}">
                                            @csrf
                                            <button type="button" class="btn btn-warning"
                                                    onclick="confirmDeactivateUser({{ $user->id }})">
                                                غیرفعال‌سازی
                                            </button>
                                        </form>
                                    @else
                                        <span style="color: #999; font-size: 11px;">حساب شما</span>
                                    @endif
                                @else
                                    <form method="POST" action="{{ route('admin.activate-user', $user) }}" style="display: inline;" id="activate-form-{{ $user->id }}">
                                        @csrf
                                        <button type="button" class="btn btn-success"
                                                onclick="confirmActivateUser({{ $user->id }})">
                                            فعال‌سازی
                                        </button>
                                    </form>
                                @endif

                                @if($user->id !== auth()->id())
                                    <form method="POST" action="{{ route('admin.delete-user', $user) }}" style="display: inline;" id="delete-form-{{ $user->id }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="button" class="btn btn-danger"
                                                onclick="confirmDeleteUser({{ $user->id }})">
                                            حذف
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            <div style="padding: 20px;">
                {{ $users->links() }}
            </div>
        @else
            <div class="empty-state">
                <h3>کاربری وجود ندارد</h3>
            </div>
        @endif
    </div>
</div>

@include('partials.alert-modal')

<script>
function confirmDeactivateUser(userId) {
    modernConfirm(
        'غیرفعال‌سازی کاربر',
        'آیا مطمئن هستید که می‌خواهید این کاربر را غیرفعال کنید؟',
        function() {
            document.getElementById('deactivate-form-' + userId).submit();
        }
    );
}

function confirmActivateUser(userId) {
    modernConfirm(
        'فعال‌سازی کاربر',
        'آیا مطمئن هستید که می‌خواهید این کاربر را فعال کنید؟',
        function() {
            document.getElementById('activate-form-' + userId).submit();
        }
    );
}

function confirmDeleteUser(userId) {
    modernConfirm(
        'حذف کاربر',
        'آیا مطمئن هستید که می‌خواهید این کاربر را حذف کنید؟ این عمل غیرقابل برگشت است.',
        function() {
            document.getElementById('delete-form-' + userId).submit();
        }
    );
}
</script>
@endsection