<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Forgot Password</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
            --success-bg: #d1e7dd;
            --success-text: #0f5132;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background: linear-gradient(135deg, var(--background-gradient-start), var(--background-gradient-end));
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
        }
        .help-text {
            font-size: 12px;
            color: #666;
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
    </style>
</head>
<body>

<div class="container">
    <h2>فراموشی رمز عبور</h2>

    <form method="POST" action="{{ route('password.email') }}">
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
            <div class="help-text">ایمیل حساب کاربری خود را وارد کنید</div>
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