<!DOCTYPE html>
<html lang="fa">
<head>
    <meta charset="UTF-8">
    <title>تاریخچه سود و زیان</title>
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
            margin: 0 5px;
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
        .pagination {
            margin-top: 20px;
            display: flex;
            justify-content: center;
        }
    </style>
</head>
<body>

<div class="container">
    <h2>تاریخچه سود و زیان</h2>

    <div class="nav-links">
        <a href="{{ route('order.create') }}">ثبت سفارش جدید</a>
        <a href="{{ route('orders.index') }}">مشاهده لیست سفارش‌ها</a>
    </div>

    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Symbol</th>
                    <th>Side</th>
                    <th>Qty</th>
                    <th>Avg. Entry Price</th>
                    <th>Avg. Exit Price</th>
                    <th>P&L</th>
                    <th>Closed At</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($positions as $position)
                    <tr>
                        <td>{{ $position->symbol }}</td>
                        <td>{{ $position->side }}</td>
                        <td>{{ rtrim(rtrim(number_format($position->qty, 8), '0'), '.') }}</td>
                        <td>{{ rtrim(rtrim(number_format($position->avg_entry_price, 4), '0'), '.') }}</td>
                        <td>{{ rtrim(rtrim(number_format($position->avg_exit_price, 4), '0'), '.') }}</td>
                        <td>
                            <span style="color: {{ $position->pnl >= 0 ? 'green' : 'red' }};">
                                {{ rtrim(rtrim(number_format($position->pnl, 4), '0'), '.') }}
                            </span>
                        </td>
                        <td>{{ \Carbon\Carbon::parse($position->closed_at)->format('Y-m-d H:i:s') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" style="text-align: center;">No closed positions found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="pagination">
        {{ $positions->links() }}
    </div>
</div>

</body>
</html>
