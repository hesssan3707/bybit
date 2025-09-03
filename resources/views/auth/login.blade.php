<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('css/fonts.css') }}">
    <style>
        :root {
            --primary-color: #007bff;
            --primary-hover: #0056b3;
            --background-gradient-start: #f0f4f8;
            --background-gradient-end: #d9e4ec;
            --form-background: #ffffff;
            --text-color: #333;
            --label-color: #555;
            --border-color: #ccc;
            --error-bg: #f8d7da;
            --error-text: #842029;
        }
        body {
            font-family: 'Yekan', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            margin: 0;
            padding: 20px;
            color: var(--text-color);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
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
            background: var(--form-background);
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
            font-weight: 600;
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
        }
        input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 8px rgba(0,123,255,0.25);
            outline: none;
        }
        button {
            width: 100%;
            padding: 14px;
            background: linear-gradient(90deg, var(--primary-color), var(--primary-hover));
            color: white;
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
    </style>
</head>
<body class="auth-page">

<div class="container">
    <h2>ورود به سیستم</h2>

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
                <div style="background: #d1e7dd; color: #0f5132; padding: 10px; border-radius: 6px; text-align: center; margin-bottom: 15px;">
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

        <button type="submit">ورود</button>
        
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

</body>
</html>
