@extends('layouts.app')

@section('title', 'پروفایل')

@push('styles')
    <style>
        /* Profile Container */
        .profile-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 25px;
        }

        /* Profile Card */
        .profile-card {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.1), rgba(255, 255, 255, 0.05));
            backdrop-filter: blur(15px);
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 30px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }

        .profile-card:hover {
            transform: translateY(-5px);
        }

        .profile-header {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .profile-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5em;
            color: white;
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
        }

        .profile-info {
            flex: 1;
        }

        .profile-name {
            margin: 0 0 8px 0;
            font-size: 1.8em;
            font-weight: 700;
            color: #fff;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .profile-email {
            margin: 0;
            color: #bbb;
            font-size: 1.1em;
            font-weight: 400;
        }

        .profile-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .profile-action-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 15px 20px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.08);
            color: #fff;
            border: 1px solid rgba(255, 255, 255, 0.1);
            font-size: 0.95em;
        }

        .profile-action-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
            background: rgba(255, 255, 255, 0.12);
            text-decoration: none;
            color: #fff;
        }

        .profile-action-btn.admin {
            background: linear-gradient(135deg, rgba(255, 107, 107, 0.2), rgba(238, 90, 36, 0.2));
            border-color: rgba(255, 107, 107, 0.3);
        }

        .profile-action-btn.admin:hover {
            background: linear-gradient(135deg, rgba(255, 107, 107, 0.3), rgba(238, 90, 36, 0.3));
        }

        .profile-action-btn.danger {
            background: linear-gradient(135deg, rgba(255, 71, 87, 0.2), rgba(196, 69, 105, 0.2));
            border-color: rgba(255, 71, 87, 0.3);
        }

        .profile-action-btn.danger:hover {
            background: linear-gradient(135deg, rgba(255, 71, 87, 0.3), rgba(196, 69, 105, 0.3));
        }

        /* Balance Cards */
        .balance-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }

        .balance-card {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.1), rgba(255, 255, 255, 0.05));
            backdrop-filter: blur(15px);
            border-radius: 18px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 25px;
            display: flex;
            flex-direction: column;
            gap: 15px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .balance-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #4CAF50, #45a049);
        }

        .balance-card.wallet::before {
            background: linear-gradient(90deg, #2196F3, #1976D2);
        }

        .balance-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
        }

        .balance-icon {
            width: 60px;
            height: 60px;
            border-radius: 15px;
            background: linear-gradient(135deg, #4CAF50, #45a049);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5em;
            color: white;
            box-shadow: 0 8px 25px rgba(76, 175, 80, 0.3);
        }

        .balance-card.wallet .balance-icon {
            background: linear-gradient(135deg, #2196F3, #1976D2);
            box-shadow: 0 8px 25px rgba(33, 150, 243, 0.3);
        }

        .balance-content h3 {
            margin: 0 0 10px 0;
            font-size: 1.1em;
            font-weight: 600;
            color: #bbb;
        }

        .balance-amount {
            display: flex;
            align-items: baseline;
            gap: 8px;
            margin-bottom: 8px;
        }

        .balance-amount .currency {
            font-size: 1.3em;
            color: #4CAF50;
            font-weight: 700;
        }

        .balance-amount .amount {
            font-size: 2.2em;
            font-weight: 800;
            color: #fff;
        }

        .balance-label {
            font-size: 0.9em;
            color: #888;
            font-weight: 500;
        }

        .balance-status {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9em;
            font-weight: 500;
            margin-top: auto;
        }

        .balance-status.online {
            color: #4CAF50;
        }

        .balance-status.updated {
            color: #2196F3;
        }

        .balance-status i {
            font-size: 0.9em;
        }

        /* No Exchange Card */
        .no-exchange-card {
            background: linear-gradient(135deg, rgba(255, 193, 7, 0.1), rgba(255, 152, 0, 0.05));
            backdrop-filter: blur(15px);
            border-radius: 18px;
            border: 1px solid rgba(255, 193, 7, 0.2);
            padding: 40px;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 20px;
        }

        .no-exchange-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, #FFC107, #FF9800);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2em;
            color: white;
            box-shadow: 0 8px 25px rgba(255, 193, 7, 0.3);
        }

        .no-exchange-content h3 {
            margin: 0 0 10px 0;
            font-size: 1.5em;
            font-weight: 700;
            color: #fff;
        }

        .no-exchange-content p {
            margin: 0 0 20px 0;
            color: #bbb;
            font-size: 1.1em;
            line-height: 1.5;
        }

        .add-exchange-btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 15px 25px;
            background: linear-gradient(135deg, #4CAF50, #45a049);
            color: white;
            text-decoration: none;
            border-radius: 12px;
            font-weight: 600;
            font-size: 1em;
            transition: all 0.3s ease;
            box-shadow: 0 8px 25px rgba(76, 175, 80, 0.3);
        }

        .add-exchange-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 35px rgba(76, 175, 80, 0.4);
            text-decoration: none;
            color: white;
        }

        /* Exchanges Section */
        .exchanges-section {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.1), rgba(255, 255, 255, 0.05));
            backdrop-filter: blur(15px);
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 30px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .section-header h3 {
            margin: 0;
            font-size: 1.4em;
            font-weight: 700;
            color: #fff;
        }

        .exchange-count {
            background: rgba(255, 255, 255, 0.1);
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.9em;
            color: #bbb;
            font-weight: 500;
        }

        /* Single Exchange Card */
        .single-exchange-card {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 15px;
            padding: 25px;
            border: 2px solid rgba(76, 175, 80, 0.3);
        }

        .exchange-main {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .exchange-logo-container {
            position: relative;
        }

        .exchange-logo {
            width: 60px;
            height: 60px;
            border-radius: 15px;
            object-fit: cover;
            border: 2px solid rgba(255, 255, 255, 0.2);
        }

        .exchange-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: linear-gradient(135deg, #4CAF50, #45a049);
            color: white;
            padding: 4px 8px;
            border-radius: 8px;
            font-size: 0.7em;
            font-weight: 600;
            box-shadow: 0 4px 15px rgba(76, 175, 80, 0.4);
        }

        .exchange-info h4 {
            margin: 0 0 8px 0;
            font-size: 1.3em;
            font-weight: 700;
            color: #fff;
        }

        .exchange-api {
            margin: 0 0 10px 0;
            color: #888;
            font-size: 0.9em;
        }

        .exchange-status-indicator {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #4CAF50;
            font-size: 0.9em;
            font-weight: 500;
        }

        /* Multiple Exchanges Grid */
        .exchanges-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
        }

        .exchange-card {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 15px;
            padding: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px solid transparent;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .exchange-card:hover {
            background: rgba(255, 255, 255, 0.08);
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
        }

        .exchange-card.active {
            border-color: rgba(76, 175, 80, 0.5);
            background: rgba(76, 175, 80, 0.1);
        }

        .exchange-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .exchange-card-body h4 {
            margin: 0 0 8px 0;
            font-size: 1.2em;
            font-weight: 600;
            color: #fff;
        }

        .exchange-action {
            margin-top: auto;
        }

        .current-label {
            display: flex;
            align-items: center;
            gap: 6px;
            color: #4CAF50;
            font-size: 0.9em;
            font-weight: 500;
        }

        .switch-label {
            display: flex;
            align-items: center;
            gap: 6px;
            color: #bbb;
            font-size: 0.9em;
            font-weight: 500;
        }

        /* Text Color Utilities */
        .text-success { color: #28a745 !important; }
        .text-primary { color: #007bff !important; }
        .text-danger { color: #dc3545 !important; }

        /* Alert Styles */
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

        /* Exchange section styles */
        .exchange-section {
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.3);
            margin-bottom: 20px;
        }

        .current-exchange {
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .exchange-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 15px;
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
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: var(--exchange-color, #007bff);
            font-size: 18px;
            border: 2px solid var(--exchange-color, #007bff);
        }

        .exchange-details h3 {
            margin: 0;
            font-size: 1.4em;
            color: var(--exchange-color, #007bff);
        }

        .exchange-status {
            font-size: 0.9em;
            color: #666;
            margin-top: 5px;
        }

        .quick-switch {
            text-align: center;
        }

        .quick-switch h4 {
            margin-bottom: 15px;
            color: #333;
        }

        .exchange-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }

        .exchange-option {
            box-shadow: 0 8px 25px rgba(0,0,0,0.3);
            border-radius: 10px;
            padding: 15px;
            transition: all 0.3s;
            cursor: pointer;
        }
        .exchange-option .current{
            border : rgba(129, 125, 125, 0.62) 2px solid;
        }

        .exchange-option:hover {
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .exchange-option .mini-logo {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: var(--exchange-color, #007bff);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 12px;
            margin: 0 auto 10px;
        }

        .exchange-option .name {
            font-weight: bold;
            margin-bottom: 5px;
            color: var(--exchange-color, #007bff);
        }

        .exchange-option .status {
            font-size: 0.8em;
            color: #666;
        }

        .btn {
            display: inline-block;
            padding: 10px 20px;
            margin: 5px;
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
            transition: opacity 0.3s;
            border: none;
            cursor: pointer;
        }
        .btn:hover {
            opacity: 0.8;
        }

        /* Profile Information Boxes */
        .profile-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .info-box {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 0;
            overflow: hidden;
            transition: all 0.3s ease;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }

        .info-box:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.2);
            border-color: rgba(255, 255, 255, 0.3);
        }

        .info-box-header {
            background: linear-gradient(135deg, rgba(0, 123, 255, 0.8), rgba(0, 86, 179, 0.8));
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .info-box-header i {
            font-size: 24px;
            color: white;
        }

        .info-box-header h3 {
            color: white;
            margin: 0;
            font-size: 16px;
            font-weight: 600;
        }

        .info-box-content {
            padding: 25px;
        }

        .profile-detail {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding: 10px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .profile-detail:last-child {
            margin-bottom: 0;
            border-bottom: none;
        }

        .profile-detail .label {
            color: rgba(255, 255, 255, 0.7);
            font-size: 14px;
            font-weight: 500;
        }

        .profile-detail .value {
            color: white;
            font-size: 14px;
            font-weight: 600;
            direction: ltr;
            text-align: left;
        }

        .balance-amount {
            display: flex;
            align-items: baseline;
            gap: 5px;
            margin-bottom: 15px;
        }

        .balance-amount .currency {
            color: rgba(255, 255, 255, 0.7);
            font-size: 18px;
            font-weight: 500;
        }

        .balance-amount .amount {
            color: #28a745;
            font-size: 28px;
            font-weight: 700;
            text-shadow: 0 2px 4px rgba(40, 167, 69, 0.3);
        }

        .balance-status {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .balance-status i {
            font-size: 12px;
        }

        .balance-status span {
            color: rgba(255, 255, 255, 0.8);
            font-size: 13px;
            font-weight: 500;
        }

        .text-success {
            color: #28a745 !important;
        }

        .text-primary {
            color: #007bff !important;
        }

        .text-danger {
            color: #dc3545 !important;
        }
        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }
        .btn-danger {
            background-color: #dc3545;
            color: white;
        }
        .btn-success {
            background-color: #28a745;
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
        .no-exchange {
            text-align: center;
            padding: 30px;
            color: #666;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        /* Profile buttons styling */
        .profile-buttons {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 15px;
            margin: 20px 0;
            flex-wrap: wrap;
        }

        .actions-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
        }

        .actions-box .info-box-header {
            color: white;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }

        .actions-box .info-box-header i {
            color: white;
        }

        .actions-box .info-box-content {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
        }

        .action-buttons-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            padding: 10px 0;
        }

        .action-btn {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 16px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            border: 2px solid transparent;
            background: rgba(255, 255, 255, 0.9);
            color: #333;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            text-decoration: none;
        }

        .action-btn.btn-primary {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
        }

        .action-btn.btn-primary:hover {
            background: linear-gradient(135deg, #0056b3, #004085);
            color: white;
        }

        .action-btn.btn-success {
            background: linear-gradient(135deg, #28a745, #1e7e34);
            color: white;
        }

        .action-btn.btn-success:hover {
            background: linear-gradient(135deg, #1e7e34, #155724);
            color: white;
        }

        .action-btn.btn-admin {
            background: linear-gradient(135deg, #6f42c1, #5a32a3);
            color: white;
        }

        .action-btn.btn-admin:hover {
            background: linear-gradient(135deg, #5a32a3, #4c2a85);
            color: white;
        }

        .action-btn.btn-danger {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
        }

        .action-btn.btn-danger:hover {
            background: linear-gradient(135deg, #c82333, #bd2130);
            color: white;
        }

        .action-btn i {
            font-size: 16px;
            min-width: 16px;
        }

        .action-btn span {
            font-size: 14px;
        }

        /* Hide admin panel button on desktop */
        .admin-panel-btn {
            display: none;
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .profile-container {
                padding: 15px;
                gap: 20px;
            }

            .profile-card {
                padding: 20px;
                border-radius: 15px;
            }

            .profile-header {
                flex-direction: column;
                text-align: center;
                gap: 15px;
            }

            .profile-avatar {
                width: 70px;
                height: 70px;
                font-size: 2em;
            }

            .profile-name {
                font-size: 1.5em;
            }

            .profile-actions {
                grid-template-columns: 1fr;
                gap: 12px;
            }

            .balance-cards {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .balance-card {
                padding: 20px;
            }

            .balance-amount .amount {
                font-size: 1.8em;
            }

            .exchanges-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .exchange-main {
                flex-direction: column;
                text-align: center;
                gap: 15px;
            }

            .exchanges-section {
                padding: 20px;
            }

            .section-header {
                flex-direction: column;
                gap: 10px;
                text-align: center;
            }
        }
    </style>
@endpush

@section('content')
    <div class="profile-container">
        <!-- User Profile Card -->
        <div class="profile-card">
            <div class="profile-header">
                <div class="profile-avatar">
                    <i class="fas fa-user-circle"></i>
                </div>
                <div class="profile-info">
                    <h2 class="profile-name">{{ $user->name ?? 'کاربر' }}</h2>
                    <p class="profile-email">{{ $user->email }}</p>
                </div>
            </div>
            
            <div class="profile-actions">
                <a href="{{ route('password.change.form') }}" class="profile-action-btn">
                    <i class="fas fa-key"></i>
                    <span>تغییر رمز عبور</span>
                </a>
                <a href="{{ route('account-settings.index') }}" class="profile-action-btn">
                    <i class="fas fa-cog"></i>
                    <span>تنظیمات</span>
                </a>
                <a href="{{ route('exchanges.index') }}" class="profile-action-btn">
                    <i class="fas fa-exchange-alt"></i>
                    <span>مدیریت صرافی‌ها</span>
                </a>
                @if(auth()->user()->isAdmin())
                    <a href="{{ route('admin.all-users') }}" class="profile-action-btn admin">
                        <i class="fas fa-user-shield"></i>
                        <span>پنل مدیریت</span>
                    </a>
                @endif
                <a href="#" onclick="event.preventDefault(); document.getElementById('logout-form').submit();" class="profile-action-btn danger">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>خروج از حساب</span>
                </a>
            </div>
        </div>

        <!-- Balance Cards -->
        @if($currentExchange)
            <div class="balance-cards">
                <div class="balance-card equity">
                    <div class="balance-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="balance-content">
                        <h3>موجودی کل</h3>
                        <div class="balance-amount">
                            <span class="currency">$</span>
                            <span class="amount">{{ $totalEquity }}</span>
                        </div>
                        <div class="balance-label">{{ $currentExchange->exchange_display_name }}</div>
                    </div>
                    <div class="balance-status online">
                        <i class="fas fa-circle"></i>
                        <span>آنلاین</span>
                    </div>
                </div>

                <div class="balance-card wallet">
                    <div class="balance-icon">
                        <i class="fas fa-wallet"></i>
                    </div>
                    <div class="balance-content">
                        <h3>کیف پول</h3>
                        <div class="balance-amount">
                            <span class="currency">$</span>
                            <span class="amount">{{ $totalBalance }}</span>
                        </div>
                        <div class="balance-label">{{ $currentExchange->exchange_display_name }}</div>
                    </div>
                    <div class="balance-status updated">
                        <i class="fas fa-sync-alt"></i>
                        <span>به‌روزرسانی شده</span>
                    </div>
                </div>
            </div>
        @else
            <div class="no-exchange-card">
                <div class="no-exchange-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="no-exchange-content">
                    <h3>صرافی فعالی ندارید</h3>
                    <p>برای شروع معاملات، ابتدا یک صرافی اضافه کنید</p>
                    <a href="{{ route('exchanges.create') }}" class="add-exchange-btn">
                        <i class="fas fa-plus"></i>
                        افزودن صرافی
                    </a>
                </div>
            </div>
        @endif

        <!-- Exchange Management Section -->
        @if($activeExchanges->count() > 0)
            <div class="exchanges-section">
                <div class="section-header">
                    <h3>صرافی‌های شما</h3>
                    <span class="exchange-count">{{ $activeExchanges->count() }} صرافی</span>
                </div>

                @if($activeExchanges->count() == 1)
                    <!-- Single Exchange Display -->
                    <div class="single-exchange-card">
                        <div class="exchange-main">
                            <div class="exchange-logo-container">
                                <img src="{{ asset('public/logos/' . strtolower($currentExchange->exchange_display_name) . '-logo.png') }}" 
                                     alt="{{ $currentExchange->exchange_display_name }}" 
                                     class="exchange-logo">
                                <div class="exchange-badge active">فعال</div>
                            </div>
                            <div class="exchange-info">
                                <h4>{{ $currentExchange->exchange_display_name }}</h4>
                                <p class="exchange-api">کلید API: {{ $currentExchange->masked_api_key }}</p>
                                <div class="exchange-status-indicator">
                                    <i class="fas fa-check-circle"></i>
                                    <span>صرافی پیش‌فرض شما</span>
                                </div>
                            </div>
                        </div>
                    </div>
                @else
                    <!-- Multiple Exchanges Grid -->
                    <div class="exchanges-grid">
                        @foreach($activeExchanges as $exchange)
                            <div class="exchange-card {{ $exchange->is_default ? 'active' : '' }}"
                                 onclick="switchExchange({{ $exchange->id }})">
                                <div class="exchange-card-header">
                                    <img src="{{ asset('public/logos/' . strtolower($exchange->exchange_display_name) . '-logo.png') }}" 
                                         alt="{{ $exchange->exchange_display_name }}" 
                                         class="exchange-logo">
                                    @if($exchange->is_default)
                                        <div class="exchange-badge active">فعال</div>
                                    @endif
                                </div>
                                <div class="exchange-card-body">
                                    <h4>{{ $exchange->exchange_display_name }}</h4>
                                    <p class="exchange-api">{{ $exchange->masked_api_key }}</p>
                                    <div class="exchange-action">
                                        @if($exchange->is_default)
                                            <span class="current-label">
                                                <i class="fas fa-check"></i>
                                                صرافی فعال
                                            </span>
                                        @else
                                            <span class="switch-label">
                                                <i class="fas fa-exchange-alt"></i>
                                                انتخاب به عنوان فعال
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif
    </div>

    @include('partials.alert-modal')

     <!-- Logout Form -->
     <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
         @csrf
     </form>

     <script>
        function switchExchange(exchangeId) {
            modernConfirm(
                'آیا می‌خواهید به این صرافی تغییر دهید؟',
                function() {
                    try {
                        // Check if CSRF token exists
                        const csrfToken = document.querySelector('meta[name="csrf-token"]');
                        if (!csrfToken) {
                            throw new Error('CSRF token not found');
                        }

                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.action = `{{ url('/exchanges') }}/${exchangeId}/switch`;

                        const csrfInput = document.createElement('input');
                        csrfInput.type = 'hidden';
                        csrfInput.name = '_token';
                        csrfInput.value = csrfToken.getAttribute('content');

                        form.appendChild(csrfInput);
                        document.body.appendChild(form);
                        form.submit();
                    } catch (error) {
                        console.error('Error switching exchange:', error);
                        modernAlert('خطا در تغییر صرافی. لطفاً دوباره تلاش کنید.', 'error');
                    }
                },
                'تغییر صرافی'
            );
        }
    </script>
@endsection
