<!DOCTYPE html>
<html lang="fa">
<head>
    <meta charset="UTF-8">
    <title>ثبت سفارش</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            font-family: 'Tahoma', sans-serif;
            padding: 15px;
            background: linear-gradient(135deg, #f0f4f8, #d9e4ec);
            direction: rtl;
        }
        h2 {
            text-align: center;
            color: #333;
            margin-bottom: 15px;
        }
        form {
            background: #fff;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            max-width: 420px;
            margin: auto;
        }
        label {
            display: block;
            margin-top: 12px;
            font-weight: bold;
            color: #555;
        }
        input {
            width: 100%;
            padding: 10px;
            margin-top: 6px;
            border: 1px solid #ccc;
            border-radius: 8px;
            font-size: 15px;
            transition: 0.3s;
        }
        input:focus {
            border-color: #007bff;
            box-shadow: 0 0 6px rgba(0,123,255,0.3);
            outline: none;
        }
        button {
            width: 100%;
            padding: 12px;
            background: linear-gradient(90deg, #007bff, #0056b3);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            margin-top: 20px;
            cursor: pointer;
            transition: background 0.3s ease-in-out;
        }
        button:hover {
            background: linear-gradient(90deg, #0056b3, #003f7f);
        }
        .alert {
            padding: 10px;
            margin-bottom: 10px;
            border-radius: 8px;
            text-align: center;
        }
        .success {
            background: #d1e7dd;
            color: #0f5132;
        }
        .error {
            background: #f8d7da;
            color: #842029;
        }
    </style>
</head>
<body>

<h2>ثبت سفارش جدید</h2>

@if(session('success'))
    <div class="alert success">{{ session('success') }}</div>
@endif

@if($errors->any())
    <div class="alert error">
        <ul style="margin:0; padding:0; list-style:none;">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form action="{{ route('order.store') }}" method="POST">
    @csrf

    <label>Entry 1 (پایین‌ترین نقطه ورود):</label>
    <input type="number" name="entry1" step="0.01" required>

    <label>Entry 2 (بالاترین نقطه ورود):</label>
    <input type="number" name="entry2" step="0.01" required>

    <label>Take Profit (TP):</label>
    <input type="number" name="tp" step="0.01" required>

    <label>Stop Loss (SL):</label>
    <input type="number" name="sl" step="0.01" required>

    <input type="hidden" name="steps" value="4">

    <label>مدت انقضای سفارش (دقیقه):</label>
    <input type="number" name="expire" min="1" value="10" required>

    <button type="submit">ارسال سفارش</button>
</form>

</body>
</html>
