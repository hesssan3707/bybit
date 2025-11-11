@extends('layouts.app')

@section('body-class', 'admin-page')

@section('title', 'همه درخواست‌های دسترسی صرافی شرکت')

@push('styles')
<style>
    .admin-container { max-width: 1200px; margin:0 auto; padding:20px; }
    .admin-header { background: rgba(255,255,255,0.95); border-radius:20px; padding:24px; margin-bottom:20px; text-align:center; }
    .admin-nav { display:flex; justify-content:center; gap:10px; flex-wrap:wrap; }
    .nav-btn { background: linear-gradient(135deg, #667eea, #764ba2); color:white; padding:10px 18px; border-radius:50px; text-decoration:none; }
    .nav-btn.active { background: linear-gradient(135deg, #764ba2, #667eea); }
    .content-card { background: rgba(255,255,255,0.95); border-radius:20px; padding:20px; }
    .exchanges-table { width:100%; border-collapse: collapse; }
    th, td { padding:12px; border-bottom:1px solid #eee; text-align:right; }
    th { background:#f8f9fa; }
    .badge { display:inline-block; background:#e9ecef; color:#333; border-radius:6px; padding:4px 8px; font-size:12px; margin-right:6px; }
    .badge-demo { background:#cff4fc; color:#055160; }
    .badge-live { background:#d1e7dd; color:#0f5132; }
    .status-approved { background:#d1e7dd; color:#0f5132; }
    .status-pending { background:#fff3cd; color:#664d03; }
    .status-rejected { background:#f8d7da; color:#842029; }
    .exchange-logo { width:30px; height:30px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; color:white; font-size:12px; margin-left:8px; }
    @media (max-width: 768px) { .content-card { overflow-x:auto; } }
</style>
@endpush

@section('content')
<div class="admin-container">
    <div class="admin-header">
        <div class="admin-nav" style="margin-top:10px;">
            <a href="{{ route('admin.pending-users') }}" class="nav-btn">کاربران در انتظار تأیید</a>
            <a href="{{ route('admin.all-users') }}" class="nav-btn">همه کاربران</a>
            <a href="{{ route('admin.pending-exchanges') }}" class="nav-btn">درخواست‌های فعال‌سازی صرافی</a>
            <a href="{{ route('admin.all-exchanges') }}" class="nav-btn">همه صرافی‌ها</a>
            <a href="{{ route('admin.tickets') }}" class="nav-btn active">تیکت‌ها</a>
            <a href="{{ route('admin.company-requests.pending') }}" class="nav-btn">در انتظار</a>
            <a href="{{ route('admin.company-requests.all') }}" class="nav-btn active">همه درخواست‌ها</a>
        </div>
    </div>

    <div class="content-card">
        <table class="exchanges-table">
            <thead>
                <tr>
                    <th>کاربر</th>
                    <th>صرافی</th>
                    <th>نوع حساب</th>
                    <th>وضعیت</th>
                    <th>تاریخ ثبت</th>
                    <th>تاریخ پردازش</th>
                    <th>حساب اختصاص داده‌شده</th>
                </tr>
            </thead>
            <tbody>
            @foreach($requests as $req)
                <tr>
                    <td>{{ $req->user->name }} (ID: {{ $req->user->id }})</td>
                    <td>
                        <span>{{ $req->getExchangeDisplayNameAttribute() }}</span>
                        <span class="exchange-logo" style="background-color: {{ $req->exchange_color }};">{{ strtoupper(substr($req->exchange_name,0,2)) }}</span>
                    </td>
                    <td>
                        @if($req->account_type === 'demo')
                            <span class="badge badge-demo">دمو</span>
                        @else
                            <span class="badge badge-live">واقعی</span>
                        @endif
                    </td>
                    <td>
                        @if($req->status === 'approved')
                            <span class="badge status-approved">تأیید شده</span>
                        @elseif($req->status === 'pending')
                            <span class="badge status-pending">در انتظار</span>
                        @else
                            <span class="badge status-rejected">رد شده</span>
                        @endif
                    </td>
                    <td>{{ optional($req->requested_at)->format('Y-m-d H:i') }}</td>
                    <td>{{ optional($req->processed_at)->format('Y-m-d H:i') }}</td>
                    <td>
                        @if($req->assignedExchange)
                            #{{ $req->assignedExchange->id }} - {{ $req->assignedExchange->exchange_display_name }}
                        @else
                            —
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
        <div style="margin-top:12px;">
            {{ method_exists($requests, 'links') ? $requests->links() : '' }}
        </div>
    </div>
</div>
@endsection