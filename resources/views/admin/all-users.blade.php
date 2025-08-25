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
        border-bottom: 1px solid #eee;
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
    .nav-links {
        margin-bottom: 20px;
        text-align: center;
    }
    .nav-links a {
        display: inline-block;
        padding: 10px 20px;
        margin: 0 5px;
        background-color: var(--primary-color);
        color: white;
        text-decoration: none;
        border-radius: 8px;
        font-weight: bold;
    }
    .nav-links a:hover {
        opacity: 0.8;
    }
</style>
@endpush

@section('content')
<div class="container">
    <div class="admin-header">
        <h2>مدیریت کاربران - همه کاربران</h2>
    </div>

    <div class="nav-links">
        <a href="{{ route('admin.pending-users') }}">کاربران در انتظار تأیید</a>
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
                            <td>{{ $user->username }}</td>
                            <td>{{ $user->email }}</td>
                            <td>
                                <span class="status-badge {{ $user->is_active ? 'status-active' : 'status-inactive' }}">
                                    {{ $user->is_active ? 'فعال' : 'غیرفعال' }}
                                </span>
                            </td>
                            <td>{{ $user->created_at->format('Y-m-d H:i') }}</td>
                            <td>{{ $user->activated_at ? $user->activated_at->format('Y-m-d H:i') : '-' }}</td>
                            <td>{{ $user->activatedBy ? $user->activatedBy->username : '-' }}</td>
                            <td>
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