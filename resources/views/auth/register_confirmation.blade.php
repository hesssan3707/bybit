<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Confirmation Code</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
        .links {
            text-align: center;
            margin-top: 20px;
        }
        .links a {
            color: var(--primary-color);
            text-decoration: none;
            margin: 0 10px;
        }
        .links a:hover {
            text-decoration: underline;
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
        .alert-error {
            background-color: var(--error-bg);
            color: var(--error-text);
            border: 1px solid #f5c2c7;
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
</head>
<body>
<div class="container glass-card">
    <h2>تایید ایمیل</h2>
    @if(session('success'))
        <div class="alert">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-error">
            <ul style="margin: 0; padding-right: 20px;">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    <form method="POST" action="{{ route('register.confirmation.verify') }}">
        @csrf
        <div class="form-group">
            <label for="code">کد تایید ارسال شده به ایمیل</label>
            <input id="code" type="text" name="code" maxlength="5" pattern="\d{5}" required autofocus>
        </div>
        <button type="submit">تایید و تکمیل ثبت نام</button>
    </form>
</div>
</body>
</html>
