<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('css/fonts.css') }}">
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
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-image:
                linear-gradient(rgba(2,6,23,0.55), rgba(2,6,23,0.55)),
                url("{{ asset('public/images/auth-background2.png') }}");
            background-attachment: fixed;
            background-size: cover;
            background-position: center;
            color: var(--text-color);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            direction: rtl;
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
            direction: ltr;
        }
        input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 8px rgba(0,123,255,0.25);
            outline: none;
        }
        button {
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
        button:hover {
            opacity: 0.9;
        }
        .invalid-feedback {
            color: var(--error-text);
            font-size: 14px;
            margin-top: 5px;
            display: block;
            text-align: right;
        }
        .help-text {
            font-size: 12px;
            color: #c5bfbf;
            margin-top: 5px;
        }
        .info-box {
            background: #e7f3ff;
            border: 1px solid #b8d4fd;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 20px;
            font-size: 14px;
            color: #004085;
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
        /* Glass + fade + alerts */
        .glass-card { background: rgba(255,255,255,0.08); backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.18); border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.25); }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(6px);} to { opacity: 1; transform: translateY(0);} }
        .fade-in { animation: fadeIn 0.5s ease-out; }
        .alert { padding: 12px 16px; border-radius: 10px; margin: 10px 0 20px; border: 1px solid transparent; }
        .alert-success { background: rgba(34,197,94,0.12); color: #22c55e; border-color: rgba(34,197,94,0.25); }
        .alert-danger, .alert-error { background: rgba(239,68,68,0.12); color: #ef4444; border-color: rgba(239,68,68,0.25); }
        .alert-warning { background: rgba(245,158,11,0.12); color: #f59e0b; border-color: rgba(245,158,11,0.25); }
        .alert-info { background: rgba(59,130,246,0.12); color: #3b82f6; border-color: rgba(59,130,246,0.25); }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert.auto-dismiss');
            alerts.forEach(function(el) {
                setTimeout(() => { el.style.transition = 'opacity .35s ease'; el.style.opacity = '0'; setTimeout(() => el.remove(), 400); }, 4000);
            });
        });
    </script>
</head>
<body>

<div class="container glass-card fade-in">
    <h2>عضویت جدید</h2>

    <form method="POST" action="{{ route('register') }}">
        @csrf

        @if($errors->any())
            <div class="form-group">
                <div class="alert alert-error">
                    <ul style="margin: 0; padding-right: 20px;">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        <div class="form-group">
            <label for="email">ایمیل (نام کاربری)</label>
            <input id="email" type="email" name="email" autocomplete="off" value="{{ old('email') }}" required autofocus>
        </div>

        <div class="form-group">
            <label for="password">رمز عبور</label>
            <div class="password-field">
                <input id="password" type="password" name="password" placeholder="حداقل 8 کاراکتر" required>
                <span class="password-toggle" onclick="togglePassword('password')">
                    <i id="password-icon" class="fas fa-eye"></i>
                </span>
            </div>
        </div>

        <div class="form-group">
            <label for="password_confirmation">تکرار رمز عبور</label>
            <div class="password-field">
                <input id="password_confirmation" type="password" name="password_confirmation" required>
                <span class="password-toggle" onclick="togglePassword('password_confirmation')">
                    <i id="password_confirmation-icon" class="fas fa-eye"></i>
                </span>
            </div>
        </div>

        <button type="submit">ثبت نام</button>

        <div style="text-align: center; margin-top: 20px;">
            <a href="{{ route('login') }}" style="color: var(--primary-color); text-decoration: none;">
                قبلاً عضو هستید؟ ورود
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

</body>
</html>