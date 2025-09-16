<!DOCTYPE html>
<html lang="fa">
<head>
    <meta charset="UTF-8">
    <title>صفحه مورد نظر یافت نشد</title>
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
                url("{{ asset('public/images/background2.png') }}");
            background-attachment: fixed;
            background-size: cover;
            background-position: center;
            color: var(--text-color);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            direction:rtl;
            text-align : center;
        }
        .container {
            width: 100%;
            max-width: 400px;
            margin: auto;
        }
        @media (max-width: 600px) {
            .container {
                max-width: 95vw;
                padding: 10px;
            }
            .glass-card {
                padding: 16px;
            }
            h1 {
                font-size: 2.2rem;
            }
            a {
                font-size: 15px;
                padding: 12px;
                text-decoration: none;
            }
        }
        a {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: bold;
            margin-top: 10px;
            cursor: pointer;
            transition: opacity 0.3s;
            text-decoration: none;
        }
        a:hover {
            opacity: 0.9;
        }
        /* Glass card, animations, and alerts */
        .glass-card { background: rgba(255,255,255,0.08); backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.18); border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.25);padding:30px }

    </style>
</head>
<body>
<div class="container glass-card">
    <h1>404</h1>
    <p>صفحه مورد نظر یافت نشد</p>
    <a class="back-link" href="{{route('futures.orders')}}">بازگشت به خانه</a>
</div>
</body>
</html>
