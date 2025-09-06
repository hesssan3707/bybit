<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Bybit Trading Helper')</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('css/fonts.css') }}">
    <style>
        :root {
            --primary-color: #007bff;
            --primary-hover: #0056b3;
            --text-color: #000000;
            --muted-text: #cbd5e1;
            --bg-overlay: rgba(2, 6, 23, 0.55); /* overlay for readability */
            --admin-bg: #0f172a; /* slate-900 */
            --docs-bg: #08234a; /* light neutral */
            --glass-bg: rgba(255, 255, 255, 0.08);
            --glass-border: rgba(255, 255, 255, 0.18);
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            margin: 0;
            padding: 80px 20px 20px 20px; /* Add padding for header */
            direction: rtl;
            color: var(--text-color);
            min-height: 100vh;
            background-attachment: fixed;
            background-size: cover;
            background-position: center;
            position: relative;
        }
        /* Background themes */
        body.bg-general {
            background-image:
                linear-gradient(rgba(2,6,23,0.65), rgba(2,6,23,0.65)),
                url("{{ asset('public/images/background.png') }}");
        }
        body.bg-spot {
            background-image:
                linear-gradient(rgba(2,6,23,0.65), rgba(2,6,23,0.65)),
                url("{{ asset('public/images/background2.png') }}");
        }
        body.bg-auth {
            background-image:
                linear-gradient(rgba(2,6,23,0.55), rgba(2,6,23,0.55)),
                url("{{ asset('public/images/auth-background2.png') }}");
        }
        body.bg-admin {
            background: var(--admin-bg);
            color: #000000;
        }
        body.bg-docs {
            background: var(--docs-bg);
            color: #1f2937;
        }

        input[type="email"], input[type="password"], input[type="text"], input[type="number"] {
            padding-right: 12px;
            padding-left: 12px;
        }

        /* Form elements */
        .form-group { text-align: right; }
        label { text-align: right; display: block; }
        .invalid-feedback { text-align: right; }
        .help-text { text-align: right; color: var(--muted-text); }

        main {
            width: 100%;
            max-width: 1200px;
            margin: auto;
        }

        /* Glassmorphism utility */
        .glass-card {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid var(--glass-border);
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.25);
        }

        /* Simplified alert styles */
        .alert {
            padding: 12px 16px;
            border-radius: 10px;
            margin: 10px 0 20px;
            border: 1px solid transparent;
        }
        .alert-success { background: rgba(34,197,94,0.12); color: #22c55e; border-color: rgba(34,197,94,0.25); }
        .alert-danger, .alert-error { background: rgba(239,68,68,0.12); color: #ef4444; border-color: rgba(239,68,68,0.25); }
        .alert-warning { background: rgba(245,158,11,0.12); color: #f59e0b; border-color: rgba(245,158,11,0.25); }
        .alert-info { background: rgba(59,130,246,0.12); color: #3b82f6; border-color: rgba(59,130,246,0.25); }
        /* CTA link inside alerts - simple button look */
        .alert .alert-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 8px 14px;
            border-radius: 10px;
            text-decoration: none;
            background: var(--primary-color);
            color: #020202;
            font-weight: 600;
            transition: transform .15s ease, opacity .2s ease;
        }
        .alert .alert-link:hover { opacity: .92; text-decoration: none; transform: translateY(-1px); }

        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(6px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes fadeOut {
            from { opacity: 1; transform: translateY(0); }
            to { opacity: 0; transform: translateY(6px); }
        }
        .fade-in { animation: fadeIn 0.5s ease-out; }
        .fade-out { animation: fadeOut 0.35s ease-in forwards; }

        /* Mobile-specific padding for bottom nav */
        @media screen and (max-width: 768px) {
            body { padding: 20px 10px 80px 10px; }
        }
    </style>
    @stack('styles')
</head>
@php
    $bodyClass = (request()->is('login') || request()->is('register') || request()->is('password/forgot') || request()->is('password/reset*'))
        ? 'bg-auth'
        : (request()->is('spot*') ? 'bg-spot'
        : (request()->is('admin*') ? 'bg-admin'
        : (request()->routeIs('api.documentation') ? 'bg-docs' : 'bg-general')));
@endphp
<body class="{{ $bodyClass }}">

    @include('layouts.navigation')

    <main class="fade-in">
        @yield('content')
    </main>

    @stack('scripts')

    <script>
    function togglePassword(fieldId) {
        const field = document.getElementById(fieldId);
        const icon = document.getElementById(fieldId + '-icon');

        if (field.type === 'password') {
            field.type = 'text';
            icon.className = 'fas fa-eye-slash';
        } else {
            field.type = 'password';
            icon.className = 'fas fa-eye';
        }
    }
        // Auto-dismiss alerts with fade-out when marked
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert.auto-dismiss');
            alerts.forEach(function(el) {
                setTimeout(() => {
                    el.classList.add('fade-out');
                    setTimeout(() => el.remove(), 400);
                }, 4000);
            });

            // Auto-upgrade links inside alerts with specific texts to styled CTAs
            const matchTexts = [
                'برای فعال‌سازی صرافی کلیک کنید',
                'رفتن به صفحه پروفایل'
            ];
            document.querySelectorAll('.alert a').forEach(a => {
                const t = (a.textContent || '').trim();
                if (matchTexts.some(m => t.includes(m))) {
                    a.classList.add('alert-link');
                }
            });
        });
    </script>
</body>
</html>
