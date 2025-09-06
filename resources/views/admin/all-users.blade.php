@extends('layouts.app')

@section('title', 'همه کاربران')

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
    .users-table {
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
    .btn-primary {
        background-color: var(--primary-color);
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
    .status-inactive {
        background-color: #f8d7da;
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
<div class="container">
    <div class="admin-header">
        <h2>مدیریت کاربران - همه کاربران</h2>
    </div>

    <div class="admin-nav-links">
        <a href="{{ route('admin.pending-exchanges') }}">درخواست‌های صرافی</a>
        <a href="{{ route('admin.all-exchanges') }}">همه صرافی‌ها</a>
        <a href="{{ route('admin.all-users') }}" class="active">همه کاربران</a>
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

    <div class="users-table">
        @if($users->count() > 0)
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
                                        <form method="POST" action="{{ route('admin.deactivate-user', $user) }}" style="display: inline;">
                                            @csrf
                                            <button type="submit" class="btn btn-warning"
                                                    onclick="return confirm('آیا مطمئن هستید که می‌خواهید این کاربر را غیرفعال کنید؟')">
                                                غیرفعال‌سازی
                                            </button>
                                        </form>
                                    @else
                                        <span style="color: #999; font-size: 11px;">حساب شما</span>
                                    @endif
                                @else
                                    <form method="POST" action="{{ route('admin.activate-user', $user) }}" style="display: inline;">
                                        @csrf
                                        <button type="submit" class="btn btn-success"
                                                onclick="return confirm('آیا مطمئن هستید که می‌خواهید این کاربر را فعال کنید؟')">
                                            فعال‌سازی
                                        </button>
                                    </form>
                                @endif

                                @if($user->id !== auth()->id())
                                    <form method="POST" action="{{ route('admin.delete-user', $user) }}" style="display: inline;">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger"
                                                onclick="return confirm('آیا مطمئن هستید که می‌خواهید این کاربر را حذف کنید؟ این عمل غیرقابل برگشت است.')">
                                            حذف
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

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
@endsection
