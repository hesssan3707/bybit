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
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.05), rgba(255, 255, 255, 0.01));
            backdrop-filter: blur(15px);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
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
            text-align: right;
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
            text-shadow:none;
        }

        .profile-email {
            margin: 0;
            color: #bbb;
            font-size: 1.1em;
            font-weight: 400;
            text-align: right;
            direction: ltr;
            unicode-bidi: embed;
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
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.01), rgba(255, 255, 255, 0.05));
            backdrop-filter: blur(15px);
            border-radius: 18px;
            padding: 25px;
            display: flex;
            flex-direction: column;
            gap: 15px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
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
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.01), rgba(255, 255, 255, 0.05));
            backdrop-filter: blur(15px);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
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
            gap:10px;
        }

        /* Investors Section Styles */
        .investors-section {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.01), rgba(255, 255, 255, 0.05));
            backdrop-filter: blur(15px);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
        }

        .investor-form {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 25px;
        }

        .investor-form h4 {
            color: #fff;
            margin-bottom: 15px;
            font-size: 1.1em;
        }

        .investor-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }

        .investor-card {
            background: rgba(255, 255, 255, 0.08);
            border-radius: 12px;
            padding: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .investor-info {
            display: flex;
            flex-direction: column;
        }

        .investor-name {
            color: #fff;
            font-weight: 600;
        }

        .investor-email {
            color: #888;
            font-size: 0.85em;
        }
        .investor-balance-pill {
            margin-top: 8px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 10px;
            border-radius: 999px;
            background: rgba(15, 23, 42, 0.55);
            border: 1px solid rgba(148, 163, 184, 0.18);
            box-shadow: 0 10px 18px rgba(0,0,0,0.18);
        }
        .investor-balance-label {
            font-size: 11px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: rgba(148, 163, 184, 0.9);
        }
        .investor-balance-value {
            font-size: 13px;
            font-weight: 700;
            color: #e5e7eb;
        }

        .name-edit-btn {
            background: rgba(255, 255, 255, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.25);
            color: #fff;
            width: 34px;
            height: 34px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: transform 0.15s ease, background 0.2s ease;
        }
        .name-edit-btn:hover {
            transform: translateY(-1px);
            background: rgba(255, 255, 255, 0.22);
        }
        .name-inline-editor {
            display: none;
            gap: 8px;
            align-items: center;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 8px;
        }
        .name-inline-editor input {
            max-width: 320px;
            width: 100%;
            padding: 10px 14px;
            border-radius: 12px;
            border: 2px solid rgba(255, 255, 255, 0.25);
            background: rgba(255, 255, 255, 0.12);
            color: #fff;
            outline: none;
        }
        .name-inline-editor .mini-btn {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .name-inline-editor .mini-btn.save { background: linear-gradient(135deg, #28a745, #1e7e34); color: #fff; }
        .name-inline-editor .mini-btn.cancel { background: linear-gradient(135deg, #6c757d, #495057); color: #fff; }

        .form-row {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .form-group {
            flex: 1;
            min-width: 200px;
        }

        .form-group label {
            display: block;
            color: #bbb;
            margin-bottom: 5px;
            font-size: 0.9em;
        }

        .form-control {
            width: 100%;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            padding: 10px;
            color: #fff;
            outline: none;
        }

        .form-control:focus {
            border-color: #667eea;
        }

        .submit-btn {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            margin-top: 10px;
        }

        .delete-btn {
            color: #ff4757;
            background: none;
            border: none;
            cursor: pointer;
            padding: 5px;
        }

        .investor-edit-btn {
            background: rgba(102, 126, 234, 0.2);
            border: 1px solid rgba(102, 126, 234, 0.35);
            color: #c7d2fe;
            width: 34px;
            height: 34px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: transform 0.15s ease, background 0.2s ease, border-color 0.2s ease;
        }
        .investor-edit-btn:hover {
            transform: translateY(-1px);
            background: rgba(102, 126, 234, 0.28);
            border-color: rgba(102, 126, 234, 0.55);
        }

        @media (max-width: 768px) {
            .form-row {
                flex-direction: column;
            }
            .form-group {
                width: 100%;
            }
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
                text-align: right;
                gap: 15px;
            }
            .profile-info {
                width: 100%;
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
                    <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap; text-align:right;margin-bottom:10px">
                        <h2 class="profile-name" id="profileNameText" style="margin:0;">{{ $user->name ?? 'کاربر' }}</h2>
                        <button type="button" class="name-edit-btn" id="profileNameEditBtn" aria-label="ویرایش نام" title="ویرایش نام">
                            <i class="fas fa-pen"></i>
                        </button>
                    </div>
                    <div class="name-inline-editor" id="profileNameEditor">
                        <input type="text" id="profileNameInput" value="{{ $user->name ?? '' }}" maxlength="255" />
                        <button type="button" class="mini-btn save" id="profileNameSaveBtn" aria-label="ذخیره" title="ذخیره">
                            <i class="fas fa-check"></i>
                        </button>
                        <button type="button" class="mini-btn cancel" id="profileNameCancelBtn" aria-label="انصراف" title="انصراف">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <p class="profile-email">{{ $user->email }}</p>
                </div>
            </div>

            <div class="profile-actions">
                <a href="{{ route('password.change.form') }}" class="profile-action-btn">
                    <i class="fas fa-key"></i>
                    <span>تغییر رمز عبور</span>
                </a>
                @if(!$user->isInvestor())
                    <a href="{{ route('account-settings.index') }}" class="profile-action-btn">
                        <i class="fas fa-cog"></i>
                        <span>تنظیمات</span>
                    </a>
                    <a href="{{ route('exchanges.index') }}" class="profile-action-btn">
                        <i class="fas fa-exchange-alt"></i>
                        <span>مدیریت صرافی‌ها</span>
                    </a>
                @endif
                @if(auth()->user()->isAdmin())
                    <a href="{{ route('admin.all-users') }}" class="profile-action-btn admin">
                        <i class="fas fa-user-shield"></i>
                        <span>پنل مدیریت</span>
                    </a>
                @endif
                @if(!$user->isInvestor())
                    <a href="#" onclick="event.preventDefault(); openTicketModal();" class="profile-action-btn">
                        <i class="fas fa-headset"></i>
                        <span>پشتیبانی</span>
                    </a>
                @endif
                <a href="#" onclick="event.preventDefault(); document.getElementById('logout-form').submit();" class="profile-action-btn danger">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>خروج از حساب</span>
                </a>
            </div>
        </div>

        @if(!$user->isInvestor() && $user->hasRealAccount())
        <!-- Investors Management Section -->
        <div class="investors-section">
            <div class="section-header">
                <h3><i class="fas fa-users-viewfinder"></i> مدیریت سرمایه‌گذاران</h3>
                <span class="exchange-count" dir="ltr">{{ $investors->count() }} / 3</span>
            </div>

            @if(session('success'))
                <div class="alert alert-success">
                    {{ session('success') }}
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger" style="background-color: #f8d7da; color: #842029; border: 1px solid #f5c2c7; padding: 12px; border-radius: 8px; margin-bottom: 20px;">
                    {{ session('error') }}
                </div>
            @endif

            @if($investors->count() < 3)
                <div class="investor-form" style="display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap;">
                    <div style="color:#fff; font-weight:600;">
                        <i class="fas fa-plus-circle"></i>
                        افزودن سرمایه‌گذار جدید
                    </div>
                    <button type="button" class="submit-btn" onclick="openInvestorCreateModal()">ایجاد سرمایه‌گذار</button>
                </div>
            @endif

            <div class="investor-grid">
                @forelse($investors as $investor)
                    <div class="investor-card">
                        <div class="investor-info">
                            <span class="investor-name">{{ $investor->name }}</span>
                            <span class="investor-email">{{ $investor->real_email }}</span>
                            <div class="investor-balance-pill" dir="ltr">
                                <span class="investor-balance-label">Balance</span>
                                <span class="investor-balance-value">{{ number_format((float)($investorBalances[$investor->id] ?? 0), 2) }} USDT</span>
                            </div>
                            @if($investor->investment_limit)
                            <div class="investor-balance-pill" dir="ltr" style="margin-top: 4px; border-color: rgba(99, 102, 241, 0.3);">
                                <span class="investor-balance-label" style="color: rgba(165, 180, 252, 0.9);">Limit</span>
                                <span class="investor-balance-value" style="color: #c7d2fe;">{{ number_format($investor->investment_limit, 2) }} USDT</span>
                            </div>
                            @endif
                        </div>
                        <div style="display:flex; gap:8px; align-items:center;">
                            <button type="button" class="investor-edit-btn" onclick="openInvestorEditModal({{ $investor->id }}, @js($investor->name), @js($investor->real_email), @js($investor->investment_limit))" title="ویرایش" aria-label="ویرایش">
                                <i class="fas fa-user-pen"></i>
                            </button>
                            <form action="{{ route('profile.investors.destroy', $investor->id) }}" method="POST" class="modern-confirm-form" data-title="حذف سرمایه‌گذار" data-message="آیا از حذف این سرمایه‌گذار اطمینان دارید؟">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="delete-btn" title="حذف" aria-label="حذف">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                @empty
                    <p style="color: #888; text-align: center; grid-column: 1/-1;">هنوز هیچ سرمایه‌گذاری اضافه نشده است.</p>
                @endforelse
            </div>

            <div id="investorCreateModal" class="ticket-modal-overlay" style="display:none;">
                <div class="ticket-modal-container">
                    <div class="ticket-modal-content">
                        <div class="ticket-modal-header">
                            <div class="ticket-modal-title">
                                <i class="fas fa-users-viewfinder"></i>
                                <h3>ایجاد سرمایه‌گذار</h3>
                            </div>
                            <button type="button" class="ticket-modal-close" onclick="closeInvestorCreateModal()">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <div class="ticket-modal-body">
                            <form action="{{ route('profile.investors.store') }}" method="POST" id="investorCreateForm">
                                @csrf
                                <div class="form-group">
                                    <label class="form-label">نام سرمایه‌گذار</label>
                                    <input type="text" name="name" class="form-control" placeholder="مثلاً: علی رضایی" required maxlength="255">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">ایمیل سرمایه‌گذار</label>
                                    <input type="email" name="email" class="form-control" placeholder="example@gmail.com" required maxlength="255">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">رمز عبور</label>
                                    <input type="password" name="password" class="form-control" placeholder="********" required minlength="8">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">حداکثر سرمایه‌گذاری (USDT)</label>
                                    <input type="number" name="investment_limit" class="form-control" placeholder="مثلاً: 1000" min="0" step="0.01">
                                    <small style="color: #888; font-size: 0.8em; margin-top: 4px; display: block;">خالی گذاشتن به معنای عدم محدودیت است.</small>
                                </div>
                                <button type="submit" class="btn-submit">
                                    <span class="btn-text">
                                        <i class="fas fa-check"></i>
                                        ثبت سرمایه‌گذار
                                    </span>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <div id="investorEditModal" class="ticket-modal-overlay" style="display:none;">
                <div class="ticket-modal-container">
                    <div class="ticket-modal-content">
                        <div class="ticket-modal-header">
                            <div class="ticket-modal-title">
                                <i class="fas fa-user-pen"></i>
                                <h3>ویرایش سرمایه‌گذار</h3>
                            </div>
                            <button type="button" class="ticket-modal-close" onclick="closeInvestorEditModal()">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <div class="ticket-modal-body">
                            <form method="POST" id="investorEditForm">
                                @csrf
                                <div class="form-group">
                                    <label class="form-label">نام سرمایه‌گذار</label>
                                    <input type="text" name="name" class="form-control" id="investorEditName" required maxlength="255">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">ایمیل سرمایه‌گذار</label>
                                    <input type="email" name="email" class="form-control" id="investorEditEmail" required maxlength="255">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">رمز عبور سرمایه‌گذار جدید (اختیاری)</label>
                                    <input type="password" name="password" class="form-control" placeholder="********" minlength="8">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">حداکثر سرمایه‌گذاری (USDT)</label>
                                    <input type="number" name="investment_limit" class="form-control" id="investorEditLimit" placeholder="مثلاً: 1000" min="0" step="0.01">
                                    <small style="color: #888; font-size: 0.8em; margin-top: 4px; display: block;">خالی گذاشتن به معنای عدم محدودیت است.</small>
                                </div>
                                <button type="submit" class="btn-submit">
                                    <span class="btn-text">
                                        <i class="fas fa-save"></i>
                                        ذخیره تغییرات
                                    </span>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif

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
            @if(!auth()->user()?->isInvestor())
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
                                @if(!auth()->user()?->isInvestor())
                                    <p class="exchange-api">API Key: {{ $currentExchange->masked_api_key }}</p>
                                @endif
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
                                    @if(!auth()->user()?->isInvestor())
                                        <p class="exchange-api">{{ $exchange->masked_api_key }}</p>
                                    @endif
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

    @include('partials.ticket-modal')

    @include('partials.alert-modal')

     <!-- Logout Form -->
     <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
         @csrf
     </form>

     <script>
        /**
         * Switch between exchanges by submitting a form with CSRF token
         * @param {number} exchangeId - The ID of the exchange to switch to
         */
        function switchExchange(exchangeId) {
            modernConfirm(
                'تغییر صرافی',
                'آیا می‌خواهید به این صرافی تغییر دهید؟',
                function() {
                    try {
                        // Verify the presence of CSRF token in the document
                        const csrfTokenMeta = document.querySelector('meta[name="csrf-token"]');
                        if (!csrfTokenMeta) {
                            throw new Error('CSRF token meta tag not found in document');
                        }

                        const csrfTokenValue = csrfTokenMeta.getAttribute('content');
                        if (!csrfTokenValue || csrfTokenValue.trim() === '') {
                            throw new Error('CSRF token value is empty');
                        }

                        // Validate exchangeId parameter
                        if (!exchangeId || isNaN(exchangeId)) {
                            throw new Error('Invalid exchange ID provided');
                        }

                        // Create dynamic form with POST method
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.action = `{{ url('/exchanges') }}/${exchangeId}/switch`;
                        form.style.display = 'none'; // Hide the form

                        // Create hidden input field containing the CSRF token
                        const csrfInput = document.createElement('input');
                        csrfInput.type = 'hidden';
                        csrfInput.name = '_token';
                        csrfInput.value = csrfTokenValue;

                        // Append CSRF token to form
                        form.appendChild(csrfInput);

                        // Append form to document body and submit
                        document.body.appendChild(form);

                        // Submit the form
                        form.submit();

                        // Clean up - remove form after submission
                        setTimeout(() => {
                            if (form.parentNode) {
                                form.parentNode.removeChild(form);
                            }
                        }, 100);

                    } catch (error) {
                        console.error('Error switching exchange:', error);

                        // Handle different types of errors appropriately
                        let errorMessage = 'خطا در تغییر صرافی. لطفاً دوباره تلاش کنید.';

                        if (error.message.includes('CSRF')) {
                            errorMessage = 'خطای امنیتی. لطفاً صفحه را تازه‌سازی کنید و دوباره تلاش کنید.';
                        } else if (error.message.includes('exchange ID')) {
                            errorMessage = 'شناسه صرافی نامعتبر است.';
                        }

                        modernAlert(errorMessage, 'error');
                    }
                }
            );
        }

        function openInvestorCreateModal() {
            const modal = document.getElementById('investorCreateModal');
            if (!modal) return;
            if (modal.parentElement !== document.body) {
                document.body.appendChild(modal);
            }
            modal.style.zIndex = '20000';
            modal.style.display = 'flex';
            setTimeout(function() { modal.classList.add('show'); }, 10);
        }

        function closeInvestorCreateModal() {
            const modal = document.getElementById('investorCreateModal');
            if (!modal) return;
            modal.classList.remove('show');
            setTimeout(function() { modal.style.display = 'none'; }, 300);
        }

        function openInvestorEditModal(id, name, email, limit) {
            const modal = document.getElementById('investorEditModal');
            const form = document.getElementById('investorEditForm');
            const nameInput = document.getElementById('investorEditName');
            const emailInput = document.getElementById('investorEditEmail');
            const limitInput = document.getElementById('investorEditLimit');
            if (!modal || !form || !nameInput || !emailInput || !limitInput) return;

            nameInput.value = name || '';
            emailInput.value = email || '';
            limitInput.value = limit || '';
            form.action = `{{ url('/profile/investors') }}/${id}`;

            if (modal.parentElement !== document.body) {
                document.body.appendChild(modal);
            }
            modal.style.zIndex = '20000';
            modal.style.display = 'flex';
            setTimeout(function() { modal.classList.add('show'); }, 10);
        }

        function closeInvestorEditModal() {
            const modal = document.getElementById('investorEditModal');
            if (!modal) return;
            modal.classList.remove('show');
            setTimeout(function() { modal.style.display = 'none'; }, 300);
        }

        document.addEventListener('DOMContentLoaded', function() {
            const flashSuccess = @js(session('success'));
            const flashError = @js(session('error'));
            if (typeof showToast === 'function') {
                if (flashSuccess) showToast(flashSuccess, 'success', 4000);
                if (flashError) showToast(flashError, 'error', 5500);
            }

            document.querySelectorAll('.modern-confirm-form').forEach(function(form) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    const title = form.getAttribute('data-title') || 'تایید اقدام';
                    const message = form.getAttribute('data-message') || 'آیا از انجام این عملیات مطمئن هستید؟';
                    modernConfirm(title, message, function() { form.submit(); });
                });
            });

            const nameText = document.getElementById('profileNameText');
            const editBtn = document.getElementById('profileNameEditBtn');
            const editor = document.getElementById('profileNameEditor');
            const input = document.getElementById('profileNameInput');
            const saveBtn = document.getElementById('profileNameSaveBtn');
            const cancelBtn = document.getElementById('profileNameCancelBtn');

            const showEditor = function() {
                if (!editor || !input || !nameText) return;
                editor.style.display = 'flex';
                if (editBtn) editBtn.style.display = 'none';
                input.focus();
                input.select();
            };

            const hideEditor = function() {
                if (!editor || !input) return;
                editor.style.display = 'none';
                if (editBtn) editBtn.style.display = 'inline-flex';
                input.value = nameText ? (nameText.textContent || '').trim() : input.value;
            };

            const saveName = async function() {
                if (!input) return;
                const newName = (input.value || '').trim();
                if (!newName) {
                    modernAlert('نام الزامی است.', 'error');
                    return;
                }

                try {
                    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                    const resp = await fetch(`{{ route('profile.update-name') }}`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken || '',
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({ name: newName })
                    });

                    const json = await resp.json().catch(function() { return null; });
                    if (!resp.ok || !json || !json.success) {
                        modernAlert((json && json.message) ? json.message : 'خطا در ذخیره نام.', 'error');
                        return;
                    }

                    if (nameText) nameText.textContent = json.name || newName;
                    hideEditor();
                    showToast('نام با موفقیت ذخیره شد.', 'success', 2500);
                } catch (e) {
                    modernAlert('خطا در ارتباط با سرور.', 'error');
                }
            };

            if (editBtn) editBtn.addEventListener('click', function() { showEditor(); });
            if (cancelBtn) cancelBtn.addEventListener('click', function() { hideEditor(); });
            if (saveBtn) saveBtn.addEventListener('click', function() { saveName(); });

            if (input) {
                input.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        saveName();
                    }
                    if (e.key === 'Escape') {
                        e.preventDefault();
                        hideEditor();
                    }
                });
            }

            const createModal = document.getElementById('investorCreateModal');
            if (createModal) {
                createModal.addEventListener('click', function(e) {
                    if (e.target === createModal) closeWatcherCreateModal();
                });
            }
            const editModal = document.getElementById('investorEditModal');
            if (editModal) {
                editModal.addEventListener('click', function(e) {
                    if (e.target === editModal) closeWatcherEditModal();
                });
            }
        });
    </script>
@endsection
