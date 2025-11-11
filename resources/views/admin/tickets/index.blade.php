@extends('layouts.app')

@section('body-class', 'admin-page')

@section('title', 'مدیریت تیکت‌ها')

@push('styles')
<style>
    * { box-sizing: border-box; }
    body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
    .admin-container { max-width: 1400px; margin: 0 auto; padding: 20px; }
    .admin-header { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); border-radius: 20px; padding: 30px; margin-bottom: 30px; text-align: center; border: 1px solid rgba(255, 255, 255, 0.2); box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1); }
    .admin-header h1 { margin: 0; color: #2c3e50; font-size: 2.5rem; font-weight: 700; background: linear-gradient(135deg, #667eea, #764ba2); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
    .admin-nav { display: flex; justify-content: center; gap: 15px; margin-top: 20px; flex-wrap: wrap; }
    .nav-btn { background: linear-gradient(135deg, #667eea, #764ba2); color: white; text-decoration: none; padding: 12px 24px; border-radius: 50px; font-weight: 600; transition: all 0.3s ease; border: none; cursor: pointer; box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3); }
    .nav-btn:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4); color: white; text-decoration: none; }
    .nav-btn.active { background: linear-gradient(135deg, #764ba2, #667eea); box-shadow: 0 6px 20px rgba(118, 75, 162, 0.4); }
    .content-card { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); border-radius: 20px; padding: 30px; border: 1px solid rgba(255, 255, 255, 0.2); box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1); }
    .table-container { overflow-x: auto; border-radius: 15px; background: white; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08); }
    table { width: 100%; border-collapse: collapse; background: white; }
    th { background: linear-gradient(135deg, #f8f9fa, #e9ecef); color: #495057; font-weight: 700; padding: 20px 15px; text-align: right; border: none; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.5px; }
    td { padding: 20px 15px; border-bottom: 1px solid #f1f3f4; color: #495057; vertical-align: middle; text-align: right; }
    tr:hover { background: rgba(102, 126, 234, 0.05); }
    .btn { display: inline-block; padding: 10px 20px; margin: 5px; text-decoration: none; border-radius: 25px; font-size: 0.85rem; font-weight: 600; border: none; cursor: pointer; transition: all 0.3s ease; text-align: center; }
    .btn-success { background: linear-gradient(135deg, #28a745, #20c997); color: white; box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3); }
    .btn-warning { background: linear-gradient(135deg, #ffc107, #ffb300); color: #212529; box-shadow: 0 4px 15px rgba(255, 193, 7, 0.3); }
    .btn-danger { background: linear-gradient(135deg, #dc3545, #e74c3c); color: white; box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3); }
    .btn-primary { background: linear-gradient(135deg, #007bff, #0056b3); color: white; box-shadow: 0 4px 15px rgba(0, 123, 255, 0.3); }
    .alert { padding: 20px; margin-bottom: 25px; border: none; border-radius: 15px; font-weight: 500; backdrop-filter: blur(10px); text-align: center; }
    @media (max-width: 768px) { th, td { padding: 12px 10px; font-size: 0.9rem; } .admin-header { padding: 20px; } .nav-btn { padding: 10px 18px; font-size: 0.9rem; } }
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

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="alert alert-error">
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <div class="content-card">
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>کاربر</th>
                        <th>عنوان</th>
                        <th>توضیحات</th>
                        <th>وضعیت</th>
                        <th>پاسخ</th>
                        <th>اقدامات</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($tickets as $t)
                        <tr>
                            <td data-label="کاربر">{{ $t->user ? ($t->user->name ?? $t->user->email) : '-' }}</td>
                            <td data-label="عنوان">{{ $t->title }}</td>
                            <td data-label="توضیحات">{{ $t->description }}</td>
                            <td data-label="وضعیت">{{ $t->status === 'closed' ? 'بسته' : 'باز' }}</td>
                            <td data-label="پاسخ">{{ $t->reply ?? '-' }}</td>
                            <td data-label="اقدامات">
                                @if($t->status !== 'closed')
                                    <form method="POST" action="{{ route('admin.tickets.reply', ['ticket' => $t->id]) }}" style="display: inline;">
                                        @csrf
                                        <input type="text" name="reply" placeholder="پاسخ ادمین" required style="padding: 8px 12px; border-radius: 10px; border: 1px solid #e0e0e0; max-width:300px;">
                                        <button type="submit" class="btn btn-primary">ارسال پاسخ</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.tickets.close', ['ticket' => $t->id]) }}" style="display: inline;">
                                        @csrf
                                        <button type="submit" class="btn btn-warning">بستن تیکت</button>
                                    </form>
                                @else
                                    <span style="color:#6c757d;">این تیکت بسته شده است</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div style="padding: 20px;">
            {{ $tickets->links() }}
        </div>
    </div>
</div>
@endsection