@extends('layouts.app')

@section('body-class', 'admin-page')

@section('title', 'درخواست‌های دسترسی صرافی شرکت (در انتظار)')

@push('styles')
<style>
    .admin-container { max-width: 1200px; margin: 0 auto; padding: 20px; }
    .admin-header { background: rgba(255,255,255,0.95); border-radius: 20px; padding: 24px; margin-bottom: 20px; text-align:center; }
    .admin-nav { display:flex; justify-content:center; gap:10px; flex-wrap:wrap; }
    .nav-btn { background: linear-gradient(135deg, #667eea, #764ba2); color:white; padding:10px 18px; border-radius:50px; text-decoration:none; }
    .nav-btn.active { background: linear-gradient(135deg, #764ba2, #667eea); }
    .content-card { background: rgba(255,255,255,0.95); border-radius: 20px; padding: 20px; }
    .request-row { border-bottom: 1px solid #eee; padding: 20px 10px; display:grid; grid-template-columns: auto 1fr; gap: 20px; align-items:flex-start; }
    .exchange-info { display:flex; align-items:center; }
    .exchange-logo { width:50px; height:50px; border-radius:50%; margin-left:15px; display:flex; align-items:center; justify-content:center; color:white; font-weight:bold; }
    .user-info { background:#f8f9fa; padding:10px; border-radius:8px; margin-bottom:10px; }
    .approval-form { background:#f8f9fa; padding:12px; border-radius:8px; }
    .approval-form input, .approval-form textarea { width:100%; padding:10px; border:1px solid #ccc; border-radius:6px; margin-top:6px; }
    .approval-actions { display:flex; gap:10px; margin-top:10px; flex-wrap:wrap; }
    .btn { border:none; border-radius:8px; padding:10px 16px; font-weight:bold; cursor:pointer; }
    .btn-success { background:#28a745; color:white; }
    .btn-danger { background:#dc3545; color:white; }
    .empty-state { text-align:center; padding:30px; color:#666; }
    @media (max-width: 768px) { .request-row { grid-template-columns: 1fr; } }
    .badge { display:inline-block; background:#e9ecef; color:#333; border-radius:6px; padding:4px 8px; font-size:12px; margin-right:6px; }
    .badge-demo { background:#cff4fc; color:#055160; }
    .badge-live { background:#d1e7dd; color:#0f5132; }
    .notes { color:#6c757d; font-size:12px; }
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
        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-error">{{ session('error') }}</div>
        @endif

        @if($requests->isEmpty())
            <div class="empty-state">هیچ درخواست در انتطار بررسی وجود ندارد.</div>
        @else
            @foreach($requests as $req)
                <div class="request-row">
                    <div class="exchange-info">
                        <img src="{{ asset('public/logos/' . $req->exchange_name . '-logo.png') }}" alt="{{ substr($req->exchange_name,0,2) }}" class="exchange-logo" style="background-color: {{ $req->exchange_color }};">
                    </div>
                    <div>
                        <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                            <strong>{{ $req->getExchangeDisplayNameAttribute() }}</strong>
                            @if($req->account_type === 'demo')
                                <span class="badge badge-demo">دمو</span>
                            @else
                                <span class="badge badge-live">واقعی</span>
                            @endif
                            <span class="badge">کاربر: {{ $req->user->name }} (ID: {{ $req->user->id }})</span>
                            <span class="notes">ثبت شده در: {{ optional($req->requested_at)->format('Y-m-d H:i') }}</span>
                        </div>
                        @if($req->user_reason)
                            <div class="user-info">دلیل کاربر: {{ $req->user_reason }}</div>
                        @endif

                        <div class="approval-form">
                            <form method="POST" action="{{ route('admin.company-requests.approve', $req) }}">
                                @csrf
                                @if($req->account_type === 'live')
                                    <label>API Key واقعی:</label>
                                    <input type="text" name="api_key" placeholder="کلید API واقعی را وارد کنید" required>
                                    <label style="margin-top:8px;">API Secret واقعی:</label>
                                    <input type="text" name="api_secret" placeholder="کلید محرمانه واقعی را وارد کنید" required>
                                @else
                                    <label>Demo API Key:</label>
                                    <input type="text" name="demo_api_key" placeholder="کلید API دمو را وارد کنید" required>
                                    <label style="margin-top:8px;">Demo API Secret:</label>
                                    <input type="text" name="demo_api_secret" placeholder="کلید محرمانه دمو را وارد کنید" required>
                                @endif
                                <label style="margin-top:8px;">یادداشت مدیر (اختیاری):</label>
                                <textarea name="admin_notes" rows="3" placeholder="توضیحات یا یادداشت برای این درخواست..."></textarea>
                                <div class="approval-actions">
                                    <button type="submit" class="btn btn-success">تأیید و تخصیص</button>
                                </div>
                            </form>

                            <form method="POST" action="{{ route('admin.company-requests.reject', $req) }}" style="margin-top:10px;">
                                @csrf
                                <textarea name="admin_notes" rows="2" placeholder="دلیل رد (اختیاری)"></textarea>
                                <div class="approval-actions">
                                    <button type="submit" class="btn btn-danger">رد درخواست</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            @endforeach
        @endif
    </div>
</div>
@endsection