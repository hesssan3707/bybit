<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <title>@yield('title', 'Bybit Trading Helper')</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        :root {
            --primary-color: #007bff;
            --primary-hover: #0056b3;
            --background-gradient-start: #f0f4f8;
            --background-gradient-end: #d9e4ec;
            --text-color: #333;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            margin: 0;
            padding: 80px 20px 20px 20px; /* Add padding for header */
            background: linear-gradient(135deg, var(--background-gradient-start), var(--background-gradient-end));
            direction: rtl;
            color: var(--text-color);
            min-height: 100vh;
        }
        
        /* RTL styles for all input fields and text areas */
        input, textarea, select {
            direction: rtl;
            text-align: right;
        }
        
        input[type="email"], input[type="password"], input[type="text"], input[type="number"] {
            direction: rtl;
            text-align: right;
            padding-right: 12px;
            padding-left: 12px;
        }
        
        /* Form elements */
        .form-group {
            text-align: right;
        }
        
        label {
            text-align: right;
            display: block;
        }
        
        /* Specific adjustments for forms */
        .invalid-feedback {
            text-align: right;
        }
        
        .help-text {
            text-align: right;
        }
        main {
            width: 100%;
            max-width: 1200px;
            margin: auto;
        }
        /* Mobile-specific padding for bottom nav */
        @media screen and (max-width: 768px) {
            body {
                padding: 20px 10px 80px 10px; /* Remove top padding, adjust others */
            }
        }
    </style>
    @stack('styles')
</head>
<body>

    @include('layouts.navigation')

    <main>
        @yield('content')
    </main>

    @stack('scripts')
</body>
</html>
