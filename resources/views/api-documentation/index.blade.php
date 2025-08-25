@extends('layouts.app')

@section('title', 'API Documentation')

@push('styles')
<style>
    .container {
        width: 100%;
        max-width: 1200px;
        margin: auto;
    }
    .doc-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 40px 30px;
        border-radius: 15px;
        margin-bottom: 30px;
        text-align: center;
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
    .doc-section {
        background: #ffffff;
        padding: 30px;
        border-radius: 15px;
        box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        margin-bottom: 30px;
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
    .endpoint {
        background: #f8f9fa;
        border: 1px solid #e9ecef;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 20px;
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
    .nav-links {
        border-radius: 10px;
        margin-bottom: 30px;
        direction: rtl;
        text-align: right;
    }
    .nav-links h3 {
        margin-top: 0;
        color: #333;
        text-align: right;
    }
    .nav-links a {
        display: inline-block;
        color: #007bff;
        text-decoration: none;
        margin: 5px 0 5px 15px;
        padding: 8px 12px;
        border-radius: 4px;
        transition: all 0.3s;
    }
    .nav-links a:hover {
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
    
    /* Mobile responsive */
    @media (max-width: 768px) {
        .container {
            padding: 0;
            margin: 0 auto;
            width: 100%;
        }
        .doc-header, .doc-section {
            margin: 10px;
            width: calc(100% - 20px);
            box-sizing: border-box;
        }
        .doc-header {
            padding: 20px;
        }
        .doc-header h1 {
            font-size: 2em;
        }
        .doc-section {
            padding: 20px;
        }
        .nav-links a {
            display: block;
            margin: 5px 0;
        }
        .param-table {
            font-size: 14px;
        }
        .code-block {
            font-size: 12px;
            padding: 15px;
        }
    }
</style>
@endpush

@section('content')
<div class="container">
    <div class="doc-header">
        <h1>🔗 مستندات API</h1>
        <p>راهنمای کامل برای نحوه استفاده از API های پلتفرم Trader Bridge</p>
    </div>

    <div class="nav-links">
        <h3>📋 فهرست محتویات</h3>
        <a href="#authentication">احراز هویت</a>
        <a href="#futures-trading">معاملات آتی</a>
        <a href="#spot-trading">معاملات اسپات</a>
        <a href="#account">مدیریت حساب</a>
        <a href="#exchanges">تنظیمات صرافی</a>
        <a href="#errors">مدیریت خطاها</a>
    </div>

    <div class="base-url">
        <strong>آدرس پایه:</strong> <code>{{ url('/api') }}</code>
    </div>

    <!-- Authentication Section -->
    <div class="doc-section" id="authentication">
        <h2>🔐 احراز هویت</h2>
        <p>تمام درخواست‌های API نیاز به احراز هویت با استفاده از Bearer token دارند. ابتدا با ورود به سیستم، یک token دریافت کنید.</p>

        <div class="endpoint">
            <h3>ورود به سیستم <span class="method post">POST</span></h3>
            <div class="endpoint-url">/api/auth/login</div>
            <p>احراز هویت کاربر و دریافت token دسترسی.</p>
            
            <h4>پارامترهای درخواست:</h4>
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
                <tr>
                    <td>exchange_name</td>
                    <td>string</td>
                    <td class="required">الزامی</td>
                    <td>صرافی مورد استفاده (bybit, binance, bingx)</td>
                </tr>
            </table>

            <h4>مثال درخواست:</h4>
            <div class="code-block">curl -X POST {{ url('/api/auth/login') }} \
  -H "Content-Type: application/json" \
  -d '{
    "email": "user@example.com",
    "password": "password123",
    "exchange_name": "bybit"
  }'</div>

            <h4>مثال پاسخ:</h4>
            <div class="code-block">{
  "success": true,
  "message": "ورود موفق",
  "data": {
    "user": {
      "id": 1,
      "email": "user@example.com"
    },
    "token": "your-api-token-here",
    "exchange": {
      "name": "Bybit",
      "color": "#f7b500"
    }
  }
}</div>
        </div>

        <div class="endpoint">
            <h3>خروج از سیستم <span class="method post">POST</span></h3>
            <div class="endpoint-url">/api/auth/logout</div>
            <p>لغو token جاری.</p>
            
            <h4>هدرها:</h4>
            <div class="code-block">Authorization: Bearer your-api-token-here</div>
        </div>
    </div>

    <!-- Futures Trading Section -->
    <div class="doc-section" id="futures-trading">
        <h2>📈 معاملات آتی</h2>

        <div class="endpoint">
            <h3>ایجاد سفارش آتی <span class="method post">POST</span></h3>
            <div class="endpoint-url">/api/store</div>
            <p>ایجاد سفارش جدید معاملات آتی با stop loss و take profit.</p>
            
            <h4>هدرها:</h4>
            <div class="code-block">Authorization: Bearer your-api-token-here
Content-Type: application/json</div>

            <h4>پارامترهای درخواست:</h4>
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
                    <td>amount</td>
                    <td>decimal</td>
                    <td class="required">الزامی</td>
                    <td>حجم موقعیت به USDT</td>
                </tr>
                <tr>
                    <td>entry_price</td>
                    <td>decimal</td>
                    <td class="required">الزامی</td>
                    <td>قیمت ورود</td>
                </tr>
                <tr>
                    <td>tp</td>
                    <td>decimal</td>
                    <td class="required">الزامی</td>
                    <td>قیمت Take Profit</td>
                </tr>
                <tr>
                    <td>sl</td>
                    <td>decimal</td>
                    <td class="required">الزامی</td>
                    <td>قیمت Stop Loss</td>
                </tr>
                <tr>
                    <td>expire_minutes</td>
                    <td>integer</td>
                    <td class="optional">اختیاری</td>
                    <td>انقضای سفارش به دقیقه (پیش‌فرض: 60)</td>
                </tr>
                <tr>
                    <td>steps</td>
                    <td>integer</td>
                    <td class="optional">اختیاری</td>
                    <td>تعداد مراحل ورود (پیش‌فرض: 1)</td>
                </tr>
            </table>

            <h4>مثال درخواست:</h4>
            <div class="code-block">curl -X POST {{ url('/api/store') }} \
  -H "Authorization: Bearer your-api-token-here" \
  -H "Content-Type: application/json" \
  -d '{
    "symbol": "ETHUSDT",
    "side": "Buy",
    "amount": 100,
    "entry_price": 2000,
    "tp": 2200,
    "sl": 1900,
    "expire_minutes": 120,
    "steps": 1
  }'</div>
        </div>

        <div class="endpoint">
            <h3>دریافت سفارشات <span class="method get">GET</span></h3>
            <div class="endpoint-url">/api/orders</div>
            <p>دریافت تمام سفارشات آتی کاربر احراز هویت شده.</p>
            
            <h4>هدرها:</h4>
            <div class="code-block">Authorization: Bearer your-api-token-here</div>

            <h4>پارامترهای کوئری:</h4>
            <table class="param-table">
                <tr>
                    <th>پارامتر</th>
                    <th>نوع</th>
                    <th>الزامی</th>
                    <th>توضیحات</th>
                </tr>
                <tr>
                    <td>status</td>
                    <td>string</td>
                    <td class="optional">اختیاری</td>
                    <td>فیلتر بر اساس وضعیت: pending, filled, expired</td>
                </tr>
                <tr>
                    <td>symbol</td>
                    <td>string</td>
                    <td class="optional">اختیاری</td>
                    <td>فیلتر بر اساس جفت ارز معاملاتی</td>
                </tr>
            </table>
        </div>

        <div class="endpoint">
            <h3>بستن سفارش <span class="method post">POST</span></h3>
            <div class="endpoint-url">/api/orders/{order_id}/close</div>
            <p>بستن دستی یک سفارش آتی.</p>
            
            <h4>هدرها:</h4>
            <div class="code-block">Authorization: Bearer your-api-token-here</div>
        </div>

        <div class="endpoint">
            <h3>دریافت تاریخچه سود و زیان <span class="method get">GET</span></h3>
            <div class="endpoint-url">/api/pnl-history</div>
            <p>دریافت تاریخچه سود و زیان برای معاملات بسته شده.</p>
            
            <h4>هدرها:</h4>
            <div class="code-block">Authorization: Bearer your-api-token-here</div>
        </div>
    </div>

    <!-- Spot Trading Section -->
    <div class="doc-section" id="spot-trading">
        <h2>💰 معاملات اسپات</h2>

        <div class="endpoint">
            <h3>ایجاد سفارش اسپات <span class="method post">POST</span></h3>
            <div class="endpoint-url">/api/spot/orders</div>
            <p>ایجاد یک سفارش معاملات اسپات جدید.</p>
            
            <h4>هدرها:</h4>
            <div class="code-block">Authorization: Bearer your-api-token-here
Content-Type: application/json</div>

            <h4>پارامترهای درخواست:</h4>
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
                    <td>order_type</td>
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

            <h4>مثال درخواست:</h4>
            <div class="code-block">curl -X POST {{ url('/api/spot/orders') }} \
  -H "Authorization: Bearer your-api-token-here" \
  -H "Content-Type: application/json" \
  -d '{
    "symbol": "ETHUSDT",
    "side": "Buy",
    "order_type": "Limit",
    "qty": 0.1,
    "price": 2000
  }'</div>
        </div>

        <div class="endpoint">
            <h3>دریافت سفارشات اسپات <span class="method get">GET</span></h3>
            <div class="endpoint-url">/api/spot/orders</div>
            <p>دریافت تمام سفارشات اسپات کاربر احراز هویت شده.</p>
            
            <h4>هدرها:</h4>
            <div class="code-block">Authorization: Bearer your-api-token-here</div>
        </div>

        <div class="endpoint">
            <h3>دریافت موجودی‌های اسپات <span class="method get">GET</span></h3>
            <div class="endpoint-url">/api/spot/balances</div>
            <p>دریافت موجودی‌های کیف پول اسپات.</p>
            
            <h4>هدرها:</h4>
            <div class="code-block">Authorization: Bearer your-api-token-here</div>
        </div>
    </div>

    <!-- Account Management Section -->
    <div class="doc-section" id="account">
        <h2>👤 مدیریت حساب کاربری</h2>

        <div class="endpoint">
            <h3>دریافت اطلاعات کاربر <span class="method get">GET</span></h3>
            <div class="endpoint-url">/api/auth/user</div>
            <p>دریافت اطلاعات کاربر احراز هویت شده.</p>
            
            <h4>هدرها:</h4>
            <div class="code-block">Authorization: Bearer your-api-token-here</div>
        </div>

        <div class="endpoint">
            <h3>تغییر رمز عبور <span class="method post">POST</span></h3>
            <div class="endpoint-url">/api/auth/change-password</div>
            <p>تغییر رمز عبور کاربر.</p>
            
            <h4>هدرها:</h4>
            <div class="code-block">Authorization: Bearer your-api-token-here
Content-Type: application/json</div>

            <h4>پارامترهای درخواست:</h4>
            <table class="param-table">
                <tr>
                    <th>پارامتر</th>
                    <th>نوع</th>
                    <th>الزامی</th>
                    <th>توضیحات</th>
                </tr>
                <tr>
                    <td>current_password</td>
                    <td>string</td>
                    <td class="required">الزامی</td>
                    <td>رمز عبور فعلی</td>
                </tr>
                <tr>
                    <td>password</td>
                    <td>string</td>
                    <td class="required">الزامی</td>
                    <td>رمز عبور جدید (حداقل 8 کاراکتر)</td>
                </tr>
                <tr>
                    <td>password_confirmation</td>
                    <td>string</td>
                    <td class="required">الزامی</td>
                    <td>تأیید رمز عبور جدید</td>
                </tr>
            </table>
        </div>
    </div>

    <!-- Exchange Configuration Section -->
    <div class="doc-section" id="exchanges">
        <h2>🔄 تنظیمات صرافی</h2>

        <div class="endpoint">
            <h3>دریافت صرافی‌های موجود <span class="method post">POST</span></h3>
            <div class="endpoint-url">/api/auth/exchanges</div>
            <p>دریافت لیست صرافی‌های موجود برای احراز هویت.</p>
            
            <h4>پارامترهای درخواست:</h4>
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
            </table>

            <h4>مثال پاسخ:</h4>
            <div class="code-block">{
  "success": true,
  "data": {
    "available_exchanges": [
      {
        "name": "bybit",
        "display_name": "Bybit",
        "color": "#f7b500",
        "is_active": true
      }
    ]
  }
}</div>
        </div>

        <div class="endpoint">
            <h3>دریافت اطلاعات صرافی <span class="method get">GET</span></h3>
            <div class="endpoint-url">/api/exchanges/{exchange_name}</div>
            <p>دریافت اطلاعات تفصیلی درباره یک صرافی خاص.</p>

            <h4>مثال پاسخ:</h4>
            <div class="code-block">{
  "success": true,
  "data": {
    "name": "bybit",
    "display_name": "Bybit",
    "color": "#f7b500",
    "api_url": "https://api.bybit.com",
    "website": "https://www.bybit.com",
    "supported_features": ["spot", "futures", "derivatives"],
    "min_order_size": 0.001
  }
}</div>
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

        <h3>خطاهای رایج</h3>
        <div class="response-example">
            <strong>توکن نامعتبر:</strong>
            <div class="code-block">{
  "success": false,
  "message": "احراز هویت نشده.",
  "error": "توکن نامعتبر یا منقضی شده است"
}</div>
        </div>

        <div class="response-example">
            <strong>خطای اعتبارسنجی:</strong>
            <div class="code-block">{
  "success": false,
  "message": "اعتبارسنجی ناموفق",
  "errors": {
    "symbol": ["فیلد symbol الزامی است."],
    "amount": ["مقدار amount باید بیشتر از 0 باشد."]
  }
}</div>
        </div>

        <div class="response-example">
            <strong>خطای دسترسی به صرافی:</strong>
            <div class="code-block">{
  "success": false,
  "message": "هیچ صرافی فعالی برای این کاربر یافت نشد"
}</div>
        </div>
    </div>

    <!-- Usage Tips Section -->
    <div class="doc-section">
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
        
        <h3>حالت سختگیرانه آتی</h3>
        <p>کاربرانی که حالت سختگیرانه آتی فعال دارند محدودیت‌های اضافی دارند:</p>
        <ul>
            <li>حساب تحت نظارت نزدیک</li>
            <li>نمی‌توان سفارشات stop loss را حذف یا تغییر داد</li>
            <li>فقط می‌توان سفارشات را از طریق این سیستم ثبت کرد</li>
            <li>حداکثر 10% ریسک در هر موقعیت</li>
            <li>وقفه 1 ساعته پس از ضرر</li>
        </ul>
    </div>
</div>
@endsection