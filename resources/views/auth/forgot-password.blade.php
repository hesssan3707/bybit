<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Forgot Password</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
            --success-bg: #d1e7dd;
            --success-text: #0f5132;
        }
        body {
            font-family: 'Yekan', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
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
            text-align: center;
        }
        .token-display {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            padding: 10px;
            margin: 15px 0;
            font-family: monospace;
            word-break: break-all;
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
    <h2>فراموشی رمز عبور</h2>

    <form method="POST" action="{{ route('password.forgot') }}">
        @csrf

        @if($errors->any())
            <div class="form-group">
                <div style="background: var(--error-bg); color: var(--error-text); padding: 10px; border-radius: 6px; margin-bottom: 15px;">
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            </div>
        @endif

        @if(session('success'))
            <div class="form-group">
                <div style="background: var(--success-bg); color: var(--success-text); padding: 10px; border-radius: 6px; margin-bottom: 15px;">
                    {{ session('success') }}

                    @if(session('reset_token'))
                        <div class="token-display">
                            <strong>لینک بازیابی (محیط تست):</strong><br>
                            <a href="{{ route('password.reset.form', ['token' => session('reset_token'), 'email' => session('user_email')]) }}"
                               style="color: var(--primary-color);">
                                کلیک کنید برای بازیابی رمز عبور
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        @endif

        <div class="form-group">
            <label for="email">ایمیل</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus>
            @error('email')
                <span class="invalid-feedback" role="alert">
                    <strong>{{ $message }}</strong>
                </span>
            @enderror
        </div>

        <button type="submit">ارسال لینک بازیابی</button>

        <div style="text-align: center; margin-top: 20px;">
            <a href="{{ route('login') }}" style="color: var(--primary-color); text-decoration: none;">
                بازگشت به صفحه ورود
            </a>
        </div>
    </form>
</div>

</body>
</html>
