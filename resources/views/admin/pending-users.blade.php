@extends('layouts.app')

@section('body-class', 'admin-page')

@section('title', 'کاربران در انتظار تأیید')

@push('styles')
<style>
    .container {
        width: 100%;
        max-width: 1000px;
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
    .empty-state {
        text-align: center;
        padding: 40px 20px;
        color: #666;
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
        <h2>مدیریت کاربران - کاربران در انتظار تأیید</h2>
    </div>

    <div class="admin-nav-links">
        <a href="{{ route('admin.pending-users') }}" class="active">کاربران در انتظار تأیید</a>
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

    <div class="users-table">
        @if($pendingUsers->count() > 0)
            <table>
                <thead>
                    <tr>
                        <th>نام کاربری</th>
                        <th>ایمیل</th>
                        <th>تاریخ ثبت‌نام</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($pendingUsers as $user)
                        <tr>
                            <td data-label="نام کاربری">{{ $user->username }}</td>
                            <td data-label="ایمیل">{{ $user->email }}</td>
                            <td data-label="تاریخ ثبت‌نام">{{ $user->created_at->format('Y-m-d H:i') }}</td>
                            <td data-label="عملیات">
                                <form method="POST" action="{{ route('admin.activate-user', $user) }}" style="display: inline;" id="activate-form-{{ $user->id }}">
                                    @csrf
                                    <button type="button" class="btn btn-success" 
                                            onclick="confirmActivateUser({{ $user->id }})">
                                        فعال‌سازی
                                    </button>
                                </form>
                                
                                <form method="POST" action="{{ route('admin.delete-user', $user) }}" style="display: inline;" id="delete-form-{{ $user->id }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="button" class="btn btn-danger" 
                                            onclick="confirmDeleteUser({{ $user->id }})">
                                        حذف
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            
            <div style="padding: 20px;">
                {{ $pendingUsers->links() }}
            </div>
        @else
            <div class="empty-state">
                <h3>کاربری در انتظار تأیید وجود ندارد</h3>
                <p>همه کاربران تأیید شده‌اند یا هیچ کاربر جدیدی ثبت‌نام نکرده است.</p>
            </div>
        @endif
    </div>
</div>

@include('partials.alert-modal')

<script>
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
        'آیا مطمئن هستید که می‌خواهید این کاربر را حذف کنید؟',
        function() {
            document.getElementById('delete-form-' + userId).submit();
        }
    );
}
</script>
@endsection