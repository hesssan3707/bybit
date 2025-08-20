@extends('layouts.app')

@section('title', 'پروفایل')

@push('styles')
<style>
    .container {
        width: 100%;
        max-width: 600px;
        margin: auto;
    }
    .profile-card {
        background: #ffffff;
        padding: 30px;
        border-radius: 15px;
        box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        text-align: center;
    }
    .profile-card h2 {
        margin-bottom: 10px;
    }
    .profile-card .username {
        font-size: 1.5em;
        font-weight: bold;
        color: var(--primary-color);
        margin-bottom: 20px;
    }
    .profile-card .equity {
        font-size: 1.2em;
        margin-bottom: 30px;
    }
    .profile-card .equity strong {
        font-size: 1.5em;
        color: #28a745;
    }
</style>
@endpush

@section('content')
<div class="container">
    <div class="profile-card">
        <h2>پروفایل کاربری</h2>
        <div class="username">{{ $user->username }}</div>
        <div class="equity">
            <p>موجودی لحظه ای حساب: <strong>{{ $totalEquity }}$</strong></p>
            <p>موجودی کیف پول: <strong>{{ $totalBalance }}$</strong></p>
        </div>
        <a href="#" onclick="event.preventDefault(); document.getElementById('logout-form').submit();" style="text-decoration: none; background-color: var(--danger-color); color: red; padding: 10px 20px; border-radius: 8px;">
            خروج از حساب
        </a>
    </div>
</div>
@endsection
