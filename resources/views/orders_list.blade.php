<!DOCTYPE html>
<html lang="fa">
<head>
    <meta charset="UTF-8">
    <title>لیست سفارش‌ها</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        :root {
            --primary-color: #007bff;
            --primary-hover: #0056b3;
            --background-gradient-start: #f0f4f8;
            --background-gradient-end: #d9e4ec;
            --table-background: #ffffff;
            --text-color: #333;
            --border-color: #dee2e6;
            --header-bg: #f8f9fa;
            --danger-color: #dc3545;
            --danger-hover: #c82333;
        }
        * {
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background: linear-gradient(135deg, var(--background-gradient-start), var(--background-gradient-end));
            direction: rtl;
            color: var(--text-color);
        }
        .container {
            width: 100%;
            max-width: 1200px;
            margin: auto;
            background: var(--table-background);
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        h2 {
            text-align: center;
            margin-bottom: 25px;
        }
        .nav-links {
            text-align: center;
            margin-bottom: 20px;
        }
        .nav-links a {
            text-decoration: none;
            background-color: var(--primary-color);
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            transition: background-color 0.3s;
        }
        .nav-links a:hover {
            background-color: var(--primary-hover);
        }
        .table-responsive {
            overflow-x: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 12px 15px;
            border: 1px solid var(--border-color);
            text-align: right;
        }
        thead {
            background-color: var(--header-bg);
        }
        tbody tr:nth-of-type(odd) {
            background-color: #f9f9f9;
        }
        .delete-btn {
            background-color: var(--danger-color);
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 6px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        .delete-btn:hover {
            background-color: var(--danger-hover);
        }
        .pagination {
            margin-top: 20px;
            display: flex;
            justify-content: center;
        }

        /* Responsive Styles */
        @media screen and (max-width: 768px) {
            body {
                padding: 10px;
            }
            .container {
                padding: 15px;
            }
            table {
                border: 0;
            }
            table thead {
                display: none;
            }
            table tr {
                display: block;
                margin-bottom: 20px;
                border: 1px solid var(--border-color);
                border-radius: 8px;
                box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            }
            table td {
                display: flex;
                justify-content: space-between;
                text-align: right;
                padding: 10px;
                border: none;
                border-bottom: 1px solid #eee;
            }
            table td:last-child {
                border-bottom: 0;
            }
            table td::before {
                content: attr(data-label);
                font-weight: bold;
                padding-left: 10px;
                text-align: left;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <h2>لیست سفارش‌های ثبت شده</h2>

    <div class="nav-links">
        <a href="{{ route('order.create') }}">ثبت سفارش جدید</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success" style="background: #d1e7dd; color: #0f5132; padding: 10px; border-radius: 8px; text-align: center; margin-bottom: 15px;">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger" style="background: #f8d7da; color: #842029; padding: 10px; border-radius: 8px; text-align: center; margin-bottom: 15px;">{{ session('error') }}</div>
    @endif

    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>جهت</th>
                    <th>قیمت ورود</th>
                    <th>مقدار</th>
                    <th>TP / SL</th>
                    <th>وضعیت</th>
                    <th>تاریخ ثبت</th>
                    <th>عملیات</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($orders as $order)
                    <tr>
                        <td data-label="جهت">{{ $order->side }}</td>
                        <td data-label="قیمت ورود">{{ number_format($order->entry_price, 2) }}</td>
                        <td data-label="مقدار">{{ number_format($order->amount, 2) }}</td>
                        <td data-label="TP / SL">{{ number_format($order->tp, 2) }} / {{ number_format($order->sl, 2) }}</td>
                        <td data-label="وضعیت">{{ $order->status }}</td>
                        <td data-label="تاریخ ثبت">{{ $order->created_at->format('Y-m-d H:i') }}</td>
                        <td data-label="عملیات">
                            <form action="{{ route('orders.destroy', $order) }}" method="POST" style="display:inline;" onsubmit="return confirm('آیا از حذف این سفارش مطمئن هستید؟');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="delete-btn">حذف</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" style="text-align: center;">هیچ سفارشی یافت نشد.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="pagination">
        {{ $orders->links() }}
    </div>
</div>

</body>
</html>
