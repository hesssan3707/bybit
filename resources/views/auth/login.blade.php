<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('public/css/fonts.css') }}">
    <style>
        :root {
            --primary-color: #e9e9ea;
            --primary-hover: #0056b3;
            --background-gradient-start: #f0f4f8;
            --background-gradient-end: #d9e4ec;
            --form-background: #ffffff;
            --text-color: #ece8e8;
            --label-color: #c5bfbf;
            --border-color: #ccc;
            --error-bg: #f8d7da;
            --error-text: #ff0016;
        }
        input, textarea, button
        {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-image:
                linear-gradient(rgba(2,6,23,0.55), rgba(2,6,23,0.55)),
                url("{{ asset('public/images/auth-background.png') }}");
            background-attachment: fixed;
            background-size: cover;
            background-position: center;
            color: var(--text-color);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            direction:rtl;
        }
        .container {
            width: 100%;
            max-width: 400px;
            margin: auto;
        }
        h2 {
            text-align: center;
            margin-bottom: 20px;
        }
        form {
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 400;
            color: var(--label-color);
            text-align: right;
        }
        input {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 14px;
            box-sizing: border-box;
            transition: border-color 0.3s, box-shadow 0.3s;
            direction:ltr;
        }
        input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 8px rgba(0,123,255,0.25);
            outline: none;
        }
        #login-btn {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: bold;
            margin-top: 10px;
            cursor: pointer;
            transition: opacity 0.3s;
        }
        #login-btn:hover {
            opacity: 0.9;
        }
        .invalid-feedback {
            color: var(--error-text);
            font-size: 14px;
            margin-top: 5px;
            display: block;
            text-align: right;
        }
        .password-field {
            position: relative;
        }
        .password-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #666;
            font-size: 18px;
            user-select: none;
        }
        .password-toggle:hover {
            color: var(--primary-color);
        }
        /* Glass card, animations, and alerts */
        .glass-card { background: rgba(255,255,255,0.08); backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.18); border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.25); }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(6px);} to { opacity: 1; transform: translateY(0);} }
        .fade-in { animation: fadeIn 0.5s ease-out; }
        .alert { padding: 12px 16px; border-radius: 10px; margin: 10px 0 20px; border: 1px solid transparent; }
        .alert-success { background: rgba(34,197,94,0.12); color: #22c55e; border-color: rgba(34,197,94,0.25); }
        .alert-danger, .alert-error { background: rgba(239,68,68,0.12); color: #ef4444; border-color: rgba(239,68,68,0.25); }
        .alert-warning { background: rgba(245,158,11,0.12); color: #f59e0b; border-color: rgba(245,158,11,0.25); }
        .alert-info { background: rgba(59,130,246,0.12); color: #3b82f6; border-color: rgba(59,130,246,0.25); }

        /* Info button and modal */
        .header-row { display: flex; align-items: center; justify-content: center; }
        .info-btn { background: transparent; border: none; color: var(--primary-color); cursor: pointer; font-size: 20px; padding: 6px; }
        .info-btn:hover { opacity: 0.85; }
        .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.6); display: flex; align-items: center; justify-content: center; z-index: 1000; padding: 20px; opacity: 0; visibility: hidden; pointer-events: none; transition: opacity 2s ease; }
        .modal-overlay.show { opacity: 1; visibility: visible; pointer-events: auto; }
        .modal-card { background: rgba(255,255,255,0.98); color: #111827; width: 100%; max-width: 520px; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.25); overflow: hidden; transform: translateY(6px); transition: transform 0.3s ease; }
        .modal-overlay.show .modal-card { transform: translateY(0); }
        .modal-header { display: flex; align-items: center; justify-content: space-between; padding: 14px 16px; border-bottom: 1px solid #e5e7eb; }
        .modal-header h3 { margin: 0; font-size: 1.1rem; color: #111827; }
        .modal-close { background: transparent; border: none; cursor: pointer; font-size: 20px; color: #6b7280; }
        .modal-body { padding: 16px; text-align: right; direction: rtl; }
        .modal-body ul { margin: 0; padding-right: 18px; }
        .modal-body li { margin: 8px 0; color: #374151; }
        .modal-footer { padding: 14px 16px; border-top: 1px solid #e5e7eb; text-align: left; }
        .modal-footer .btn-primary { background: #111827; color: white; border-radius: 8px; padding: 10px 14px; border: none; cursor: pointer; }
    </style>
</head>
<body>

<div class="container glass-card fade-in">
    <div class="header-row">
        <h2>ورود به سیستم</h2>
        <button type="button" class="info-btn" onclick="openInfoModal()" aria-label="اطلاعات" title="اطلاعات">
            <i class="fas fa-info-circle"></i>
        </button>
    </div>

    <form method="POST" action="{{ route('login') }}">
        @csrf

        @error('email')
            <div class="form-group">
                <span class="invalid-feedback" role="alert" style="text-align: right;">
                    <strong>{{ $message }}</strong>
                </span>
            </div>
        @enderror

        @if(session('success'))
            <div class="form-group">
                <div class="alert alert-success auto-dismiss" style="text-align: center;">
                    {{ session('success') }}
                </div>
            </div>
        @endif

        <div class="form-group">
            <label for="email">ایمیل</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus>
        </div>

        <div class="form-group">
            <label for="password">رمز عبور</label>
            <div class="password-field">
                <input id="password" type="password" name="password" required>
                <span class="password-toggle" onclick="togglePassword('password')">
                    <i id="password-icon" class="fas fa-eye"></i>
                </span>
            </div>
            @error('password')
                <span class="invalid-feedback" role="alert">
                    <strong>{{ $message }}</strong>
                </span>
            @enderror
        </div>

        <div class="form-group" style="display: flex; align-items: center;">
            <input type="checkbox" name="remember" id="remember" {{ old('remember') ? 'checked' : '' }} style="width: auto; margin-left: 10px;">
            <label for="remember" style="margin-bottom: 0;">
                مرا به خاطر بسپار
            </label>
        </div>

        <button type="submit" id="login-btn">ورود</button>

        <div style="text-align: center; margin-top: 20px;">
            <a href="{{ route('register') }}" style="color: var(--primary-color); text-decoration: none; margin-left: 15px;">
                عضویت جدید
            </a>
            <span style="color: #ccc;">|</span>
            <a href="{{ route('password.forgot') }}" style="color: var(--primary-color); text-decoration: none; margin-right: 15px;">
                فراموشی رمز عبور
            </a>
        </div>
    </form>
</div>
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
</script>

<script>
// Auto-dismiss alerts
(function(){
    const onReady = () => {
        const alerts = document.querySelectorAll('.alert.auto-dismiss');
        alerts.forEach(function(el){
            setTimeout(() => { el.style.transition = 'opacity .35s ease'; el.style.opacity = '0'; setTimeout(() => el.remove(), 400); }, 4000);
        });
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', onReady); else onReady();
})();
</script>

<!-- Info Modal -->
<div id="siteInfoModal" class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="siteInfoTitle">
    <div class="modal-card">
        <div class="modal-header">
            <h3 id="siteInfoTitle">آشنایی با Trader Bridge</h3>
            <button class="modal-close" onclick="closeInfoModal()" aria-label="بستن">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <p>
                Trader Bridge ابزارهای قدرتمندی برای تریدرهای حرفه‌ای ارائه می‌دهد. با اتصال به صرافی‌های فعال
                Binance، Bybit و BingX، امکان مدیریت همزمان چند اکانت کاربری فراهم شده و از طریق API، قابلیت اتوماسیون
                و توسعه یکپارچه در اختیار شما قرار می‌گیرد.
            </p>
            <ul>
                <li>مدیریت سفارش‌ها بدون وابستگی به اینترنت: عملکرد پایدار حتی با قطع VPN یا کندی اینترنت، مناسب برای تریدرهای فعال در Binance، Bybit و BingX.</li>
                <li>امنیت بالا: اتصال با IP ثابت و حذف ریسک بلاک شدن اکانت به دلیل تغییر IP.</li>
                <li>کنترل احساسات با Strict Mode: تعریف قوانین سخت‌گیرانه (مانند حد ضرر اتوماتیک) برای کاهش ضرر.</li>
                <li>معاملات گروهی در فیوچرز: همکاری آسان‌تر و توزیع ریسک بین تریدرها در صرافی‌های پشتیبانی‌شده.</li>
                <li>پشتیبانی از چند کاربر و اکانت: مدیریت چندین اکانت به‌صورت همزمان.</li>
            </ul>
            <p class="help-text">
                با ورود، شما در محیطی چندصرافی و یکپارچه برای معاملات اسپات و فیوچرز فعالیت می‌کنید.
            </p>
        </div>
        <div class="modal-footer">
            <button class="btn-primary" onclick="closeInfoModal()">متوجه شدم</button>
        </div>
    </div>
    </div>

<script>
    function openInfoModal() {
        var el = document.getElementById('siteInfoModal');
        if (el) {
            el.classList.add('show');
        }
    }
    function closeInfoModal() {
        var el = document.getElementById('siteInfoModal');
        if (el) {
            el.classList.remove('show');
        }
    }
    // Auto-show for new IPs
    document.addEventListener('DOMContentLoaded', function() {
        var autoShow = {{ isset($showInfoModal) && $showInfoModal ? 'true' : 'false' }};
        if (autoShow) {
            setTimeout(() => openInfoModal(), 3000);
        }
    });
</script>

</body>
</html>
