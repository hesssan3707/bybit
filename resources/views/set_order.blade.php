<!DOCTYPE html>
<html lang="fa">
<head>
    <meta charset="UTF-8">
    <title>ثبت سفارش جدید</title>
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
            direction: rtl;
            color: var(--text-color);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .container {
            width: 100%;
            max-width: 500px;
            margin: auto;
        }
        h2 {
            text-align: center;
            margin-bottom: 25px;
        }
        form {
            background: var(--form-background);
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        .form-group {
            margin-bottom: 15px;
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
            font-size: 16px;
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
            font-size: 18px;
            font-weight: bold;
            margin-top: 20px;
            cursor: pointer;
            transition: opacity 0.3s;
        }
        button:hover {
            opacity: 0.9;
        }
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            text-align: center;
            font-size: 16px;
        }
        .alert-success {
            background-color: var(--success-bg);
            color: var(--success-text);
        }
        .alert-danger {
            background-color: var(--error-bg);
            color: var(--error-text);
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .invalid-feedback {
            color: var(--error-text);
            font-size: 14px;
            margin-top: 5px;
            display: block;
        }
        @media (max-width: 600px) {
            body {
                padding: 15px;
            }
            form {
                padding: 20px;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <h2>ثبت سفارش جدید</h2>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if($errors->has('msg'))
        <div class="alert alert-danger">
            {{ $errors->first('msg') }}
        </div>
    @endif


    <form action="{{ route('order.store') }}" method="POST">
        @csrf

        <div class="form-group">
            <label for="entry1">Entry 1 (پایین‌ترین نقطه ورود):</label>
            <input id="entry1" type="number" name="entry1" step="any" required value="{{ old('entry1') }}">
            @error('entry1') <span class="invalid-feedback">{{ $message }}</span> @enderror
        </div>

        <div class="form-group">
            <label for="entry2">Entry 2 (بالاترین نقطه ورود):</label>
            <input id="entry2" type="number" name="entry2" step="any" required value="{{ old('entry2') }}">
            @error('entry2') <span class="invalid-feedback">{{ $message }}</span> @enderror
        </div>

        <div class="form-group">
            <label for="tp">Take Profit (TP):</label>
            <input id="tp" type="number" name="tp" step="any" required value="{{ old('tp') }}">
            @error('tp') <span class="invalid-feedback">{{ $message }}</span> @enderror
        </div>

        <div class="form-group">
            <label for="sl">Stop Loss (SL):</label>
            <input id="sl" type="number" name="sl" step="any" required value="{{ old('sl') }}">
            @error('sl') <span class="invalid-feedback">{{ $message }}</span> @enderror
        </div>

        <div class="form-group">
            <label for="steps">تعداد پله‌ها:</label>
            <input id="steps" type="number" name="steps" min="1" value="{{ old('steps', 4) }}" required>
            @error('steps') <span class="invalid-feedback">{{ $message }}</span> @enderror
        </div>

        <div class="form-group">
            <label for="expire">مدت انقضای سفارش (دقیقه):</label>
            <input id="expire" type="number" name="expire" min="1" value="{{ old('expire', 10) }}" required>
            @error('expire') <span class="invalid-feedback">{{ $message }}</span> @enderror
        </div>

        <div class="form-group">
            <label for="risk_percentage">درصد ریسک (حداکثر ۱۰٪):</label>
            <input id="risk_percentage" type="number" name="risk_percentage" min="0.1" max="10" step="0.1" value="{{ old('risk_percentage', 10) }}" required>
            @error('risk_percentage') <span class="invalid-feedback">{{ $message }}</span> @enderror
        </div>

        <hr style="border: none; border-top: 1px solid #eee; margin: 25px 0;">

        <div class="form-group">
            <label for="access_password">رمز عبور دسترسی:</label>
            <input id="access_password" type="password" name="access_password" required>
            @error('access_password') <span class="invalid-feedback">{{ $message }}</span> @enderror
        </div>

        <button type="submit">ارسال سفارش</button>
    </form>
</div>

</body>
</html>
