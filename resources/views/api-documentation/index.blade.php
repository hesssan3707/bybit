@extends('layouts.app')

@section('body-class', 'documentation-page')

@section('title', 'API Documentation')

@push('styles')
<style>
    .container {
        width: 100%;
        max-width: 1200px;
        margin: auto;
        padding: 0;
    }

    .doc-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 40px 30px;
        border-radius: 15px;
        margin-bottom: 30px;
        text-align: center;
    }

    .doc-section {
        background: #ffffff;
        padding: 30px;
        border-radius: 15px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        margin-bottom: 20px;
    }

    .endpoint {
        background: #f8f9fa;
        border: 1px solid #e9ecef;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 20px;
        transition: all 0.3s ease;
    }

    .endpoint:hover {
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }

    .doc-header h1 {
        margin: 0 0 10px 0;
        font-size: 2.5em;
    }

    .doc-header p {
        margin: 0;
        font-size: 1.1em;
        opacity: 0.9;
    }

    .doc-section h2 {
        color: #333;
        border-bottom: 3px solid #667eea;
        padding-bottom: 10px;
        margin-bottom: 25px;
        text-align: right;
    }

    .doc-section h3 {
        color: #555;
        margin-top: 30px;
        margin-bottom: 15px;
        text-align: right;
    }
    .method {
        display: inline-block;
        padding: 4px 8px;
        border-radius: 4px;
        font-weight: bold;
        font-size: 12px;
        margin-right: 10px;
    }
    .method.get { background: #28a745; color: white; }
    .method.post { background: #007bff; color: white; }
    .method.put { background: #ffc107; color: black; }
    .method.delete { background: #dc3545; color: white; }
    .endpoint-url {
        font-family: 'Courier New', monospace;
        background: #e9ecef;
        padding: 8px 12px;
        border-radius: 4px;
        margin: 10px 0;
        word-break: break-all;
        direction: ltr;
        text-align: left;
    }
    .code-block {
        background: #2d3748;
        color: #e2e8f0;
        padding: 20px;
        border-radius: 8px;
        font-family: 'Courier New', monospace;
        overflow-x: auto;
        margin: 15px 0;
        white-space: pre-wrap;
        line-height: 1.5;
        direction: ltr;
        text-align: left;
    }
    .param-table {
        width: 100%;
        border-collapse: collapse;
        margin: 15px 0;
        direction: rtl;
    }
    .param-table th,
    .param-table td {
        border: 1px solid #dee2e6;
        padding: 12px;
        text-align: right;
    }
    .param-table th {
        background: #f8f9fa;
        font-weight: bold;
    }
    .required {
        color: #dc3545;
        font-weight: bold;
    }
    .optional {
        color: #6c757d;
    }
    .doc-nav-links {
        background: #f8f9fa;
        border: 1px solid #e9ecef;
        border-radius: 10px;
        padding: 20px;
        margin-bottom: 30px;
        direction: rtl;
        text-align: right;
    }

    .doc-nav-links h3 {
        margin-top: 0;
        margin-bottom: 15px;
        color: #333;
        text-align: right;
        border-bottom: 2px solid #e9ecef;
        padding-bottom: 10px;
    }
    .doc-nav-links a {
        display: inline-block;
        color: #007bff;
        text-decoration: none;
        margin: 5px 0 5px 15px;
        padding: 8px 12px;
        border-radius: 4px;
        transition: all 0.3s;
    }
    .doc-nav-links a:hover {
        background: #007bff;
        color: white;
    }
    .base-url {
        background: #d1ecf1;
        border: 1px solid #bee5eb;
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 20px;
        direction: rtl;
        text-align: right;
    }
    .response-example {
        background: #f8f9fa;
        border: 1px solid #e9ecef;
        border-radius: 8px;
        padding: 15px;
        margin: 10px 0;
        direction: ltr;
        text-align: left;
    }

    /* Mobile-specific improvements */
    .mobile-view {
        background: #f8f9fa;
    }

    .mobile-view .doc-section {
        background: white;
        box-shadow: none;
        border: 1px solid #e9ecef;
    }

    .mobile-view .endpoint {
        background: white;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        margin-bottom: 10px;
    }

    .mobile-view .doc-nav-links {
        background: white;
        border: 1px solid #e9ecef;
    }

    /* Text Direction Styles */
    .persian-content {
        direction: rtl;
        text-align: right;
    }

    .ltr-content {
        direction: ltr;
        text-align: left;
    }

    .param-table th:first-child,
    .param-table td:first-child {
        text-align: left;
    }

    .base-url code {
        direction: ltr;
        text-align: left;
    }

    /* Mobile table wrapper */
    .table-wrapper {
        overflow-x: auto;
        margin: 15px 0;
        border-radius: 8px;
        border: 1px solid #e9ecef;
    }

    /* Collapsible sections for mobile */
    .endpoint-toggle {
        display: none;
        background: #007bff;
        color: white;
        border: none;
        padding: 10px 15px;
        width: 100%;
        text-align: right;
        border-radius: 8px;
        margin-bottom: 10px;
        cursor: pointer;
        font-size: 1em;
        direction: rtl;
    }

    .endpoint-content {
        display: block;
    }

    /* Mobile responsive */
    @media (max-width: 768px) {
        .container {
            padding: 0;
            margin: 0;
            width: 100%;
        }

        .doc-header {
            margin: 0;
            width: 100%;
            padding: 20px 15px;
            border-radius: 0;
            box-sizing: border-box;
        }

        .doc-header h1 {
            font-size: 1.8em;
            margin-bottom: 8px;
        }

        .doc-header p {
            font-size: 1em;
        }

        .doc-section {
            margin: 0;
            width: 100%;
            padding: 20px 15px;
            border-radius: 0;
            box-sizing: border-box;
            box-shadow: none;
            border-bottom: 1px solid #e9ecef;
        }

        .doc-section h2 {
            font-size: 1.4em;
            margin-bottom: 20px;
        }

        .doc-section h3 {
            font-size: 1.2em;
            margin-top: 25px;
            margin-bottom: 12px;
        }

        .doc-nav-links {
            margin: 0;
            width: 100%;
            padding: 15px;
            border-radius: 0;
            box-sizing: border-box;
            background: #f8f9fa;
            border: none;
            border-bottom: 1px solid #e9ecef;
        }

        .doc-nav-links h3 {
            text-align: center;
            margin-bottom: 15px;
        }

        .doc-nav-links a {
            display: block;
            margin: 8px 0;
            padding: 12px 15px;
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .doc-nav-links a:hover {
            background: #007bff;
            color: white;
            border-color: #007bff;
        }

        .endpoint {
            padding: 0;
            margin-bottom: 0;
            background: transparent;
            border: none;
            border-radius: 0;
            box-shadow: none;
        }

        .endpoint + .endpoint {
            border-top: 1px solid #e9ecef;
            padding-top: 20px;
        }

        .endpoint h3 {
            font-size: 1.1em;
            margin-bottom: 10px;
            line-height: 1.4;
        }

        .method {
            display: inline-block;
            margin: 8px 8px 8px 0;
            text-align: center;
            padding: 6px 12px;
            font-size: 0.8em;
        }

        .endpoint-url {
            font-size: 0.85em;
            padding: 10px;
            margin: 10px 0;
            word-break: break-all;
            overflow-wrap: break-word;
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 6px;
        }

        .endpoint-toggle {
            display: block;
            margin-bottom: 15px;
            font-size: 0.9em;
            padding: 12px 15px;
        }

        .endpoint-content {
            display: none;
            padding: 0;
        }

        .endpoint-content.active {
            display: block;
        }

        .param-table {
            font-size: 0.8em;
            width: 100%;
            margin: 15px 0;
            display: table;
            border-collapse: collapse;
        }

        .param-table th,
        .param-table td {
            padding: 8px 6px;
            border: 1px solid #dee2e6;
            word-wrap: break-word;
        }

        .param-table th {
            background: #f8f9fa;
            font-size: 0.75em;
        }

        .table-wrapper {
            width: 100%;
            overflow-x: auto;
            margin: 15px 0;
            border: 1px solid #e9ecef;
            border-radius: 6px;
            background: white;
        }

        .code-block {
            font-size: 0.75em;
            padding: 12px;
            margin: 15px 0;
            width: 100%;
            border-radius: 6px;
            overflow-x: auto;
            box-sizing: border-box;
        }

        .response-example {
            width: 100%;
            padding: 12px;
            margin: 15px 0;
            border-radius: 6px;
            box-sizing: border-box;
        }

        .base-url {
            margin: 0;
            width: 100%;
            padding: 15px;
            box-sizing: border-box;
            border-radius: 0;
            border-bottom: 1px solid #e9ecef;
        }

        .base-url code {
            font-size: 0.85em;
            word-break: break-all;
        }
    }

    /* Extra small screens */
    @media (max-width: 480px) {
        .doc-header {
            padding: 15px 10px;
        }

        .doc-header h1 {
            font-size: 1.5em;
        }

        .doc-section {
            padding: 15px 10px;
        }

        .doc-section h2 {
            font-size: 1.2em;
        }

        .doc-nav-links {
            padding: 10px;
        }

        .doc-nav-links a {
            padding: 10px 12px;
            margin: 6px 0;
            font-size: 0.9em;
        }

        .endpoint-toggle {
            padding: 10px 12px;
            font-size: 0.85em;
        }

        .method {
            padding: 4px 8px;
            font-size: 0.75em;
        }

        .param-table {
            font-size: 0.7em;
        }

        .param-table th,
        .param-table td {
            padding: 6px 4px;
        }

        .code-block {
            font-size: 0.7em;
            padding: 10px;
        }

        .endpoint-url {
            font-size: 0.8em;
            padding: 8px;
        }

        .base-url {
            padding: 10px;
        }
    }

    /* Landscape tablets */
    @media (min-width: 769px) and (max-width: 1024px) {
        .container {
            max-width: 95%;
            padding: 0 15px;
        }

        .doc-header, .doc-section {
            margin: 15px 0;
        }

        .param-table {
            font-size: 0.9em;
        }

        .code-block {
            font-size: 0.9em;
        }
    }
</style>
@endpush

@section('content')
<div class="container fade-in" id="docs-container">
    <div class="doc-header">
        <h1>🔗 مستندات API</h1>
        <p>راهنمای کامل برای نحوه استفاده از API های پلتفرم Trader Bridge</p>
    </div>

    <div class="doc-nav-links">
        <h3>📋 فهرست محتویات</h3>
        <a href="#authentication">احراز هویت</a>
        <a href="#futures-trading">معاملات آتی</a>
        <a href="#spot-trading">معاملات اسپات</a>
        <a href="#pnl-history">تاریخچه سود و زیان</a>
        <a href="#wallet-balance">موجودی کیف پول</a>
        <a href="#exchange-management">مدیریت صرافی</a>
        <a href="#market">بازار</a>
        <a href="#errors">مدیریت خطاها</a>
    </div>

    <div class="base-url">
        <strong>آدرس پایه:</strong> <code>{{ url('/api/v1') }}</code>
    </div>

    <!-- Authentication Section -->
    <div class="doc-section" id="authentication">
        <h2>🔐 احراز هویت</h2>
        <p>تمام درخواست‌های API نیاز به احراز هویت با استفاده از Bearer token دارند. ابتدا با ورود به سیستم، یک token دریافت کنید.</p>

        <div class="endpoint">
            <button class="endpoint-toggle" onclick="toggleEndpoint(this)">ورود به سیستم - دریافت Token</button>
            <div class="endpoint-content">
                <h3>ورود به سیستم <span class="method post">POST</span></h3>
                <div class="endpoint-url">/api/auth/login</div>
                <p>احراز هویت کاربر و دریافت token دسترسی.</p>

                <h4>پارامترهای درخواست:</h4>
                <div class="table-wrapper">
                    <table class="param-table">
                        <tr>
                            <th>پارامتر</th>
                            <th>نوع</th>
                            <th>الزامی</th>
                            <th>توضیحات</th>
                        </tr>
                        <tr>
                            <td>email</td>
                            <td>string</td>
                            <td class="required">الزامی</td>
                            <td>آدرس ایمیل کاربر</td>
                        </tr>
                        <tr>
                            <td>password</td>
                            <td>string</td>
                            <td class="required">الزامی</td>
                            <td>رمز عبور کاربر</td>
                        </tr>
                    </table>
                </div>

            <h4>مثال درخواست:</h4>
            <div class="code-block">curl -X POST {{ url('/api/auth/login') }} \
  -H "Content-Type: application/json" \
  -d '{
    "email": "user@example.com",
    "password": "password123"
  }'</div>

            <h4>مثال پاسخ:</h4>
            <div class="code-block">{
  "success": true,
  "message": "Login successful",
  "data": {
    "access_token": "your-access-token-here",
    "token_type": "Bearer",
    "expires_at": "2025-09-22T15:30:00.000000Z",
    "user": {
      "id": 1,
      "email": "user@example.com"
    }
  }
}</div>
        </div>

        <div class="endpoint">
            <button class="endpoint-toggle" onclick="toggleEndpoint(this)">خروج از سیستم</button>
            <div class="endpoint-content">
                <h3>خروج از سیستم <span class="method post">POST</span></h3>
                <div class="endpoint-url">/api/auth/logout</div>
                <p>لغو token جاری.</p>

                <h4>هدرها:</h4>
                <div class="code-block">Authorization: Bearer your-api-token-here</div>
            </div>
        </div>
    </div>

    <!-- Futures Trading Section -->
    <div class="doc-section" id="futures-trading">
        <h2>📈 معاملات آتی</h2>

        <div class="endpoint">
            <button class="endpoint-toggle" onclick="toggleEndpoint(this)">لیست سفارشات آتی</button>
            <div class="endpoint-content">
                <h3>لیست سفارشات آتی <span class="method get">GET</span></h3>
                <div class="endpoint-url">/futures/orders</div>
                <p>دریافت لیست تمام سفارشات آتی کاربر.</p>
                <h4>هدرها:</h4>
                <div class="code-block">Authorization: Bearer your-api-token-here</div>
                <h4>مثال پاسخ:</h4>
                <div class="code-block">{
  "success": true,
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": 1,
        "user_exchange_id": 1,
        "order_id": "12345",
        "order_link_id": "uuid-1",
        "symbol": "BTCUSDT",
        "entry_price": "50000.00",
        "tp": "52000.00",
        "sl": "48000.00",
        "steps": 1,
        "expire_minutes": 60,
        "status": "pending",
        "side": "buy",
        "amount": "0.00100000",
        "entry_low": "50000.00",
        "entry_high": "50000.00",
        "cancel_price": null,
        "created_at": "2025-09-13T03:48:00.000000Z",
        "updated_at": "2025-09-13T03:48:00.000000Z"
      }
    ]
  }
}</div>
            </div>
        </div>

        <div class="endpoint">
            <button class="endpoint-toggle" onclick="toggleEndpoint(this)">ایجاد سفارش آتی</button>
            <div class="endpoint-content">
                <h3>ایجاد سفارش آتی <span class="method post">POST</span></h3>
                <div class="endpoint-url">/futures/orders</div>
                <p>ایجاد سفارش جدید معاملات آتی با stop loss.</p>

                <h4>هدرها:</h4>
                <div class="code-block">Authorization: Bearer your-api-token-here
Content-Type: application/json</div>

                <h4>پارامترهای درخواست:</h4>
                <div class="table-wrapper">
                    <table class="param-table">
                <tr>
                    <th>پارامتر</th>
                    <th>نوع</th>
                    <th>الزامی</th>
                    <th>توضیحات</th>
                </tr>
                <tr>
                    <td>symbol</td>
                    <td>string</td>
                    <td class="required">الزامی</td>
                    <td>جفت ارز معاملاتی. بازارهای پشتیبانی شده: BTCUSDT, ETHUSDT, ADAUSDT, DOTUSDT, BNBUSDT, XRPUSDT, SOLUSDT, TRXUSDT, DOGEUSDT, LTCUSDT</td>
                </tr>
                <tr>
                    <td>entry1</td>
                    <td>numeric</td>
                    <td class="required">الزامی</td>
                    <td>قیمت ورود اول</td>
                </tr>
                <tr>
                    <td>entry2</td>
                    <td>numeric</td>
                    <td class="required">الزامی</td>
                    <td>قیمت ورود دوم</td>
                </tr>
                <tr>
                    <td>tp</td>
                    <td>numeric</td>
                    <td class="required">الزامی</td>
                    <td>قیمت Take Profit</td>
                </tr>
                <tr>
                    <td>sl</td>
                    <td>numeric</td>
                    <td class="required">الزامی</td>
                    <td>قیمت Stop Loss</td>
                </tr>
                <tr>
                    <td>steps</td>
                    <td>integer</td>
                    <td class="required">الزامی</td>
                    <td>تعداد مراحل ورود</td>
                </tr>
                <tr>
                    <td>expire</td>
                    <td>integer</td>
                    <td class="required">الزامی</td>
                    <td>انقضای سفارش به دقیقه</td>
                </tr>
                <tr>
                    <td>risk_percentage</td>
                    <td>numeric</td>
                    <td class="required">الزامی</td>
                    <td>درصد ریسک</td>
                </tr>
                 <tr>
                    <td>cancel_price</td>
                    <td>numeric</td>
                    <td class="optional">اختیاری</td>
                    <td>قیمت کنسل شدن</td>
                </tr>
            </table>
                </div>
            </div>
        </div>

        <div class="endpoint">
            <button class="endpoint-toggle" onclick="toggleEndpoint(this)">بستن سفارش آتی</button>
            <div class="endpoint-content">
                <h3>بستن سفارش آتی <span class="method post">POST</span></h3>
                <div class="endpoint-url">/futures/orders/{order}/close</div>
                <p>بستن یک سفارش آتی.</p>
                <h4>هدرها:</h4>
                <div class="code-block">Authorization: Bearer your-api-token-here</div>
                <h4>پارامترهای درخواست:</h4>
                <div class="table-wrapper">
                    <table class="param-table">
                        <tr>
                            <th>پارامتر</th>
                            <th>نوع</th>
                            <th>الزامی</th>
                            <th>توضیحات</th>
                        </tr>
                        <tr>
                            <td>price_distance</td>
                            <td>numeric</td>
                            <td class="required">الزامی</td>
                            <td>فاصله قیمت</td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <div class="endpoint">
            <button class="endpoint-toggle" onclick="toggleEndpoint(this)">حذف سفارش آتی</button>
            <div class="endpoint-content">
                <h3>حذف سفارش آتی <span class="method delete">DELETE</span></h3>
                <div class="endpoint-url">/futures/orders/{order}</div>
                <p>حذف یک سفارش آتی.</p>
                <h4>هدرها:</h4>
                <div class="code-block">Authorization: Bearer your-api-token-here</div>
            </div>
        </div>
    </div>

    <!-- Spot Trading Section -->
    <div class="doc-section" id="spot-trading">
        <h2>💰 معاملات اسپات</h2>

        <div class="endpoint">
            <button class="endpoint-toggle" onclick="toggleEndpoint(this)">لیست سفارشات اسپات</button>
            <div class="endpoint-content">
                <h3>لیست سفارشات اسپات <span class="method get">GET</span></h3>
                <div class="endpoint-url">/spot/orders</div>
                <p>دریافت تمام سفارشات اسپات کاربر احراز هویت شده.</p>
                <h4>هدرها:</h4>
                <div class="code-block">Authorization: Bearer your-api-token-here</div>
            </div>
        </div>

        <div class="endpoint">
            <button class="endpoint-toggle" onclick="toggleEndpoint(this)">نمایش سفارش اسپات</button>
            <div class="endpoint-content">
                <h3>نمایش سفارش اسپات <span class="method get">GET</span></h3>
                <div class="endpoint-url">/spot/orders/{spotOrder}</div>
                <p>دریافت اطلاعات یک سفارش اسپات.</p>
                <h4>هدرها:</h4>
                <div class="code-block">Authorization: Bearer your-api-token-here</div>
            </div>
        </div>

        <div class="endpoint">
            <button class="endpoint-toggle" onclick="toggleEndpoint(this)">ایجاد سفارش اسپات</button>
            <div class="endpoint-content">
                <h3>ایجاد سفارش اسپات <span class="method post">POST</span></h3>
                <div class="endpoint-url">/spot/orders</div>
                <p>ایجاد یک سفارش معاملات اسپات جدید.</p>

                <h4>هدرها:</h4>
                <div class="code-block">Authorization: Bearer your-api-token-here
Content-Type: application/json</div>

                <h4>پارامترهای درخواست:</h4>
                <div class="table-wrapper">
                    <table class="param-table">
                <tr>
                    <th>پارامتر</th>
                    <th>نوع</th>
                    <th>الزامی</th>
                    <th>توضیحات</th>
                </tr>
                <tr>
                    <td>symbol</td>
                    <td>string</td>
                    <td class="required">الزامی</td>
                    <td>جفت ارز معاملاتی (مثال: ETHUSDT)</td>
                </tr>
                <tr>
                    <td>side</td>
                    <td>string</td>
                    <td class="required">الزامی</td>
                    <td>جهت سفارش: Buy یا Sell</td>
                </tr>
                <tr>
                    <td>orderType</td>
                    <td>string</td>
                    <td class="required">الزامی</td>
                    <td>نوع سفارش: Market یا Limit</td>
                </tr>
                <tr>
                    <td>qty</td>
                    <td>decimal</td>
                    <td class="required">الزامی</td>
                    <td>مقدار سفارش</td>
                </tr>
                <tr>
                    <td>price</td>
                    <td>decimal</td>
                    <td class="optional">اختیاری</td>
                    <td>قیمت سفارش (برای سفارشات Limit الزامی است)</td>
                </tr>
                </table>
                </div>
            </div>
        </div>

        <div class="endpoint">
            <button class="endpoint-toggle" onclick="toggleEndpoint(this)">حذف سفارش اسپات</button>
            <div class="endpoint-content">
                <h3>حذف سفارش اسپات <span class="method delete">DELETE</span></h3>
                <div class="endpoint-url">/spot/orders/{spotOrder}</div>
                <p>حذف یک سفارش اسپات.</p>
                <h4>هدرها:</h4>
                <div class="code-block">Authorization: Bearer your-api-token-here</div>
            </div>
        </div>
    </div>

    <!-- PNL History Section -->
    <div class="doc-section" id="pnl-history">
        <h2>📊 تاریخچه سود و زیان</h2>
        <div class="endpoint">
            <button class="endpoint-toggle" onclick="toggleEndpoint(this)">دریافت تاریخچه سود و زیان</button>
            <div class="endpoint-content">
                <h3>دریافت تاریخچه سود و زیان <span class="method get">GET</span></h3>
                <div class="endpoint-url">/pnl-history</div>
                <p>دریافت تاریخچه سود و زیان برای معاملات بسته شده.</p>
                <h4>هدرها:</h4>
                <div class="code-block">Authorization: Bearer your-api-token-here</div>
            </div>
        </div>
    </div>

    <!-- Wallet Balance Section -->
    <div class="doc-section" id="wallet-balance">
        <h2>💳 موجودی کیف پول</h2>
        <div class="endpoint">
            <button class="endpoint-toggle" onclick="toggleEndpoint(this)">دریافت موجودی کیف پول</button>
            <div class="endpoint-content">
                <h3>دریافت موجودی کیف پول <span class="method get">GET</span></h3>
                <div class="endpoint-url">/balance</div>
                <p>دریافت موجودی کیف پول اسپات و آتی.</p>
                <h4>هدرها:</h4>
                <div class="code-block">Authorization: Bearer your-api-token-here</div>
            </div>
        </div>
    </div>

    <!-- Exchange Management Section -->
    <div class="doc-section" id="exchange-management">
        <h2>🔄 مدیریت صرافی</h2>
        <div class="endpoint">
            <button class="endpoint-toggle" onclick="toggleEndpoint(this)">لیست صرافی ها</button>
            <div class="endpoint-content">
                <h3>لیست صرافی ها <span class="method get">GET</span></h3>
                <div class="endpoint-url">/exchanges</div>
                <p>دریافت لیست صرافی های کاربر.</p>
                <h4>هدرها:</h4>
                <div class="code-block">Authorization: Bearer your-api-token-here</div>
            </div>
        </div>
        <div class="endpoint">
            <button class="endpoint-toggle" onclick="toggleEndpoint(this)">ایجاد صرافی</button>
            <div class="endpoint-content">
                <h3>ایجاد صرافی <span class="method post">POST</span></h3>
                <div class="endpoint-url">/exchanges</div>
                <p>ایجاد یک صرافی جدید برای کاربر.</p>
                <h4>هدرها:</h4>
                <div class="code-block">Authorization: Bearer your-api-token-here</div>
                <h4>پارامترهای درخواست:</h4>
                <div class="table-wrapper">
                    <table class="param-table">
                        <tr>
                            <th>پارامتر</th>
                            <th>نوع</th>
                            <th>الزامی</th>
                            <th>توضیحات</th>
                        </tr>
                        <tr>
                            <td>exchange_name</td>
                            <td>string</td>
                            <td class="required">الزامی</td>
                            <td>نام صرافی (bybit, bingx, binance)</td>
                        </tr>
                        <tr>
                            <td>api_key</td>
                            <td>string</td>
                            <td class="required">الزامی</td>
                            <td>کلید API</td>
                        </tr>
                        <tr>
                            <td>api_secret</td>
                            <td>string</td>
                            <td class="required">الزامی</td>
                            <td>کلید مخفی API</td>
                        </tr>
                        <tr>
                            <td>password</td>
                            <td>string</td>
                            <td class="optional">اختیاری</td>
                            <td>رمز عبور (برای برخی صرافی ها)</td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
        <div class="endpoint">
            <button class="endpoint-toggle" onclick="toggleEndpoint(this)">بروزرسانی صرافی</button>
            <div class="endpoint-content">
                <h3>بروزرسانی صرافی <span class="method put">PUT</span></h3>
                <div class="endpoint-url">/exchanges/{exchange}</div>
                <p>بروزرسانی اطلاعات یک صرافی.</p>
                <h4>هدرها:</h4>
                <div class="code-block">Authorization: Bearer your-api-token-here</div>
                <h4>پارامترهای درخواست:</h4>
                <div class="table-wrapper">
                    <table class="param-table">
                        <tr>
                            <th>پارامتر</th>
                            <th>نوع</th>
                            <th>الزامی</th>
                            <th>توضیحات</th>
                        </tr>
                        <tr>
                            <td>api_key</td>
                            <td>string</td>
                            <td class="required">الزامی</td>
                            <td>کلید API</td>
                        </tr>
                        <tr>
                            <td>api_secret</td>
                            <td>string</td>
                            <td class="required">الزامی</td>
                            <td>کلید مخفی API</td>
                        </tr>
                        <tr>
                            <td>password</td>
                            <td>string</td>
                            <td class="optional">اختیاری</td>
                            <td>رمز عبور (برای برخی صرافی ها)</td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
        <div class="endpoint">
            <button class="endpoint-toggle" onclick="toggleEndpoint(this)">حذف صرافی</button>
            <div class="endpoint-content">
                <h3>حذف صرافی <span class="method delete">DELETE</span></h3>
                <div class="endpoint-url">/exchanges/{exchange}</div>
                <p>حذف یک صرافی.</p>
                <h4>هدرها:</h4>
                <div class="code-block">Authorization: Bearer your-api-token-here</div>
            </div>
        </div>
        <div class="endpoint">
            <button class="endpoint-toggle" onclick="toggleEndpoint(this)">تغییر صرافی فعال</button>
            <div class="endpoint-content">
                <h3>تغییر صرافی فعال <span class="method post">POST</span></h3>
                <div class="endpoint-url">/exchanges/{exchange}/switch</div>
                <p>تغییر صرافی فعال کاربر. با تغییر صرافی، حالت موقعیت (Hedge/One-way) نیز بر اساس تنظیمات حالت سخت‌گیرانه کاربر تنظیم می‌شود.</p>
                <h4>هدرها:</h4>
                <div class="code-block">Authorization: Bearer your-api-token-here</div>
            </div>
        </div>
        <div class="endpoint">
            <button class="endpoint-toggle" onclick="toggleEndpoint(this)">تست اتصال صرافی</button>
            <div class="endpoint-content">
                <h3>تست اتصال صرافی <span class="method post">POST</span></h3>
                <div class="endpoint-url">/exchanges/{exchange}/test</div>
                <p>تست اتصال به صرافی.</p>
                <h4>هدرها:</h4>
                <div class="code-block">Authorization: Bearer your-api-token-here</div>
            </div>
        </div>
    </div>

    <!-- Market Section -->
    <div class="doc-section" id="market">
        <h2>📈 بازار</h2>
        <div class="endpoint">
            <button class="endpoint-toggle" onclick="toggleEndpoint(this)">دریافت بهترین قیمت</button>
            <div class="endpoint-content">
                <h3>دریافت بهترین قیمت <span class="method post">POST</span></h3>
                <div class="endpoint-url">/best-price</div>
                <p>دریافت بهترین قیمت برای یک یا چند بازار در صرافی های فعال کاربر.</p>
                <h4>هدرها:</h4>
                <div class="code-block">Authorization: Bearer your-api-token-here</div>
                <h4>پارامترهای درخواست:</h4>
                <div class="table-wrapper">
                    <table class="param-table">
                        <tr>
                            <th>پارامتر</th>
                            <th>نوع</th>
                            <th>الزامی</th>
                            <th>توضیحات</th>
                        </tr>
                        <tr>
                            <td>markets</td>
                            <td>array</td>
                            <td class="required">الزامی</td>
                            <td>آرایه ای از نام بازارها</td>
                        </tr>
                        <tr>
                            <td>type</td>
                            <td>string</td>
                            <td class="required">الزامی</td>
                            <td>نوع بازار (spot یا futures)</td>
                        </tr>
                        <tr>
                            <td>side</td>
                            <td>string</td>
                            <td class="required">الزامی</td>
                            <td>جهت معامله (buy یا sell)</td>
                        </tr>
                    </table>
                </div>
                <h4>مثال پاسخ:</h4>
                <div class="code-block">{
  "success": true,
  "data": [
    {
      "market": "BTCUSDT",
      "best_price": 50000.5,
      "exchange": "bybit"
    }
  ]
}</div>
            </div>
        </div>
    </div>


    <!-- Error Handling Section -->
    <div class="doc-section" id="errors">
        <h2>⚠️ مدیریت خطاها</h2>

        <h3>کدهای وضعیت HTTP</h3>
        <table class="param-table">
            <tr>
                <th>کد وضعیت</th>
                <th>توضیحات</th>
            </tr>
            <tr>
                <td>200</td>
                <td>موفقیت</td>
            </tr>
            <tr>
                <td>400</td>
                <td>درخواست نامعتبر - پارامترهای نامعتبر</td>
            </tr>
            <tr>
                <td>401</td>
                <td>احراز هویت نشده - توکن نامعتبر یا ناقص</td>
            </tr>
            <tr>
                <td>403</td>
                <td>دسترسی ممنوع - مجوزهای ناکافی</td>
            </tr>
            <tr>
                <td>404</td>
                <td>پیدا نشد - منبع وجود ندارد</td>
            </tr>
            <tr>
                <td>422</td>
                <td>خطای اعتبارسنجی - داده‌های ورودی نامعتبر</td>
            </tr>
            <tr>
                <td>500</td>
                <td>خطای سرور داخلی</td>
            </tr>
        </table>

        <h3>فرمت پاسخ خطا</h3>
        <div class="code-block">{
  "success": false,
  "message": "توضیح خطا",
  "errors": {
    "field_name": ["پیام خطای اعتبارسنجی"]
  }
}</div>
    </div>

    <!-- Usage Tips Section -->
    <div class="doc-section" id="usage-tips">
        <h2>💡 نکات استفاده</h2>

        <h3>محدودیت نرخ درخواست‌ها</h3>
        <p>درخواست‌های API برای جلوگیری از سوء استفاده محدود شده‌اند. اگر از حد مجاز فراتر بروید، کد وضعیت 429 دریافت خواهید کرد.</p>

        <h3>توکن احراز هویت</h3>
        <p>توکن‌ها پس از 30 روز منقضی می‌شوند. هنگام انقضای توکن باید دوباره احراز هویت کنید.</p>

        <h3>الزامات صرافی</h3>
        <p>اطمینان حاصل کنید که حساب صرافی شما مجوزهای لازم را دارد:</p>
        <ul>
            <li>دسترسی به معاملات اسپات (برای نقاط پایانی اسپات)</li>
            <li>دسترسی به معاملات آتی (برای نقاط پایانی آتی)</li>
            <li>لیست سفید IP پیکربندی شده (در صورت نیاز صرافی)</li>
        </ul>
    </div>
</div>

@push('scripts')
<script>
function toggleEndpoint(button) {
    const content = button.nextElementSibling;
    const isActive = content.classList.contains('active');

    // Close all other open endpoints on mobile for cleaner view
    if (window.innerWidth <= 768) {
        document.querySelectorAll('.endpoint-content.active').forEach(el => {
            if (el !== content) {
                el.classList.remove('active');
                const btn = el.previousElementSibling;
                if (btn && btn.classList.contains('endpoint-toggle')) {
                    btn.style.background = '#007bff';
                    btn.textContent = btn.textContent.replace(' ✓', '');
                }
            }
        });
    }

    // Toggle current endpoint with visual feedback
    if (!isActive) {
        content.classList.add('active');
        button.style.background = '#28a745';
        button.textContent = button.textContent + ' ✓';

        // Smooth scroll to the opened section
        if (window.innerWidth <= 768) {
            setTimeout(() => {
                button.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }, 100);
        }
    } else {
        content.classList.remove('active');
        button.style.background = '#007bff';
        button.textContent = button.textContent.replace(' ✓', '');
    }
}

// Auto-expand endpoints on larger screens, collapse on mobile
function handleResize() {
    const contents = document.querySelectorAll('.endpoint-content');
    const buttons = document.querySelectorAll('.endpoint-toggle');

    if (window.innerWidth > 768) {
        // Desktop view - show all content via class (no inline display)
        contents.forEach(content => {
            content.classList.add('active');
        });
        buttons.forEach(btn => {
            btn.style.background = '#007bff';
            btn.textContent = btn.textContent.replace(' ✓', '');
        });
    } else {
        // Mobile view - collapse all initially via class (no inline display)
        contents.forEach(content => {
            content.classList.remove('active');
        });
        buttons.forEach(btn => {
            btn.style.background = '#007bff';
            btn.textContent = btn.textContent.replace(' ✓', '');
        });
    }
}

// Smooth scrolling for navigation links
function setupSmoothScrolling() {
    document.querySelectorAll('.doc-nav-links a').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const targetId = this.getAttribute('href').substring(1);
            const targetElement = document.getElementById(targetId);

            if (targetElement) {
                const offsetTop = targetElement.offsetTop - 20;
                window.scrollTo({
                    top: offsetTop,
                    behavior: 'smooth'
                });
            }
        });
    });
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('docs-container');

    // Add mobile class for additional styling control
    function updateMobileClass() {
        if (window.innerWidth <= 768) {
            container.classList.add('mobile-view');
        } else {
            container.classList.remove('mobile-view');
        }
    }

    updateMobileClass();
    handleResize();
    setupSmoothScrolling();

    // Debounced resize handler for better performance
    let resizeTimeout;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(() => {
            updateMobileClass();
            handleResize();
        }, 150);
    });
});
</script>
@endpush
@endsection