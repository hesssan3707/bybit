@extends('layouts.app')

@section('title', 'درخواست‌های فعال‌سازی صرافی')

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
        padding: 8px 16px;
        margin: 4px;
        text-decoration: none;
        border-radius: 6px;
        font-size: 14px;
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
    .nav-links a.active {
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
</style>
@endpush

@section('content')
<div class="container">
    <div class="admin-header">
        <h2>مدیریت صرافی‌ها - درخواست‌های در انتظار تأیید</h2>
    </div>

    <div class="nav-links">
        <a href="{{ route('admin.pending-exchanges') }}" class="active">درخواست‌های در انتظار</a>
        <a href="{{ route('admin.all-exchanges') }}">همه صرافی‌ها</a>
        <a href="{{ route('admin.pending-users') }}">کاربران در انتظار</a>
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
                            <strong>کاربر:</strong> {{ $exchange->user->email }} ({{ $exchange->user->username }})<br>
                            <strong>تاریخ درخواست:</strong> {{ $exchange->activation_requested_at->format('Y-m-d H:i') }}<br>
                            <strong>کلید API:</strong>
                            <div class="masked-key">{{ $exchange->masked_api_key }}</div>
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
                            <form method="POST" action="{{ route('admin.approve-exchange', $exchange) }}" style="display: inline;">
                                @csrf
                                <textarea name="admin_notes" placeholder="یادداشت تأیید (اختیاری)..."></textarea>
                                <div class="approval-actions">
                                    <button type="submit" class="btn btn-success" 
                                            onclick="return confirm('آیا مطمئن هستید که می‌خواهید این درخواست را تأیید کنید؟')">
                                        ✓ تأیید
                                    </button>
                                </div>
                            </form>
                            
                            <form method="POST" action="{{ route('admin.reject-exchange', $exchange) }}" style="margin-top: 10px;">
                                @csrf
                                <textarea name="admin_notes" placeholder="دلیل رد (الزامی)..." required></textarea>
                                <div class="approval-actions">
                                    <button type="submit" class="btn btn-danger" 
                                            onclick="return confirm('آیا مطمئن هستید که می‌خواهید این درخواست را رد کنید؟')">
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
@endsection