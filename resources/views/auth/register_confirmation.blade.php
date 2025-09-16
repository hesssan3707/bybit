<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Confirmation Code</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: Arial, sans-serif; background: #f0f4f8; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .container { background: #fff; padding: 30px; border-radius: 12px; box-shadow: 0 8px 25px rgba(0,0,0,0.08); max-width: 400px; width: 100%; }
        h2 { text-align: center; margin-bottom: 20px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; font-weight: 500; }
        input { width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 8px; font-size: 16px; box-sizing: border-box; }
        button { width: 100%; padding: 14px; border: none; border-radius: 8px; font-size: 16px; font-weight: bold; margin-top: 10px; cursor: pointer; background: #22c55e; color: #fff; }
        .alert { padding: 12px 16px; border-radius: 10px; margin: 10px 0 20px; border: 1px solid transparent; background: #e7f3ff; color: #004085; }
        .alert-error { background: #f8d7da; color: #ef4444; border-color: #f5c6cb; }
    </style>
</head>
<body>
<div class="container">
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