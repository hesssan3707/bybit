@extends('layouts.app')

@section('title', 'داشبورد سرمایه‌گذار')

@push('styles')
<style>
    .investor-dashboard {
        max-width: 800px;
        margin: 20px auto;
        padding: 20px;
    }
    .settings-card {
        background: linear-gradient(135deg, rgba(255, 255, 255, 0.05), rgba(255, 255, 255, 0.01));
        backdrop-filter: blur(15px);
        border-radius: 20px;
        padding: 30px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
        color: white;
    }
    .form-group {
        margin-bottom: 20px;
    }
    .form-label {
        display: block;
        margin-bottom: 8px;
        font-weight: bold;
    }
    .form-control {
        width: 100%;
        padding: 10px;
        border-radius: 8px;
        border: 1px solid rgba(255,255,255,0.1);
        background: rgba(0,0,0,0.2);
        color: white;
    }
    .btn-save {
        background: var(--primary-color);
        color: white;
        border: none;
        padding: 12px 24px;
        border-radius: 8px;
        cursor: pointer;
        width: 100%;
        font-weight: bold;
        transition: opacity 0.3s;
    }
    .btn-save:hover {
        opacity: 0.9;
    }
    .balance-section {
        margin-bottom: 30px;
        text-align: center;
        padding: 20px;
        background: rgba(255,255,255,0.05);
        border-radius: 15px;
    }
    .balance-amount {
        font-size: 2em;
        font-weight: bold;
        color: #4ade80;
        margin: 10px 0;
        direction: ltr;
    }
    .action-buttons {
        display: flex;
        gap: 10px;
        margin-top: 15px;
    }
    .btn-action {
        flex: 1;
        padding: 10px;
        border-radius: 8px;
        border: none;
        font-weight: bold;
        cursor: pointer;
        transition: opacity 0.3s;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }
    .btn-action:hover {
        opacity: 0.9;
    }
    .btn-deposit {
        background: #10b981;
        color: white;
    }
    .btn-withdraw {
        background: #ef4444;
        color: white;
    }
</style>
@endpush

@section('content')
<div class="glass-card container investor-dashboard">
    <div class="settings-card">
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom: 30px;">
             <h2 style="margin:0;">پنل مدیریت سرمایه‌گذار</h2>
             <a href="{{ route('profile.index') }}" class="btn-action" style="background:rgba(255,255,255,0.1); color:white; flex:0 0 auto; width:auto; padding:8px 16px;">
                 بازگشت به پروفایل
             </a>
        </div>

        @if(session('success'))
            <div class="alert alert-success" style="background: rgba(16, 185, 129, 0.2); color: #4ade80; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                {{ session('success') }}
            </div>
        @endif

        <div class="balance-section">
            <h3>موجودی فعلی</h3>
            <div class="balance-amount">{{ number_format($balance, 2) }} USDT</div>
            
            <div class="action-buttons">
                <form action="{{ route('investor.deposit') }}" method="POST" style="flex:1;">
                    @csrf
                    <button type="submit" class="btn-action btn-deposit">
                        <i class="fas fa-plus-circle"></i> افزایش موجودی
                    </button>
                </form>
                <form action="{{ route('investor.withdraw') }}" method="POST" style="flex:1;">
                    @csrf
                    <button type="submit" class="btn-action btn-withdraw">
                        <i class="fas fa-minus-circle"></i> برداشت وجه
                    </button>
                </form>
            </div>
        </div>

        <form action="{{ route('investor.settings.update') }}" method="POST">
            @csrf
            
            <div class="form-group">
                <label class="form-label">وضعیت سرمایه‌گذاری</label>
                <div style="display: flex; gap: 20px; align-items: center; background: rgba(0,0,0,0.2); padding: 15px; border-radius: 8px;">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; color: #fff;">
                        <input type="radio" name="is_trading_enabled" value="1" {{ $settings->is_trading_enabled ? 'checked' : '' }}>
                        <span>فعال (شرکت در معاملات)</span>
                    </label>
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; color: #fff;">
                        <input type="radio" name="is_trading_enabled" value="0" {{ !$settings->is_trading_enabled ? 'checked' : '' }}>
                        <span>غیرفعال (توقف معاملات)</span>
                    </label>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">درصد تخصیص سرمایه به معاملات (%)</label>
                <input type="number" name="allocation_percentage" class="form-control" 
                       value="{{ $settings->allocation_percentage }}" min="0" max="100" required>
                <small style="color: #ccc; margin-top: 8px; display: block;">
                    مشخص کنید چه درصدی از موجودی شما در معاملات استفاده شود.
                </small>
            </div>

            <button type="submit" class="btn-save">ذخیره تنظیمات</button>
        </form>
    </div>
</div>
@endsection
