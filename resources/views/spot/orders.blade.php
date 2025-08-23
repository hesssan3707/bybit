@extends('layouts.app')

@section('title', 'Spot Orders')

@push('styles')
<style>
    .container {
        background: #ffffff;
        padding: 20px;
        border-radius: 15px;
        box-shadow: 0 8px 25px rgba(0,0,0,0.1);
    }
    h2 {
        text-align: center;
        margin-bottom: 25px;
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
        border: 1px solid #dee2e6;
        text-align: right;
    }
    thead {
        background-color: #f8f9fa;
    }
    tbody tr:nth-of-type(odd) {
        background-color: #f9f9f9;
    }
    .status-badge {
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 12px;
        font-weight: bold;
        color: white;
    }
    .status-success { background-color: #28a745; }
    .status-warning { background-color: #ffc107; color: #212529; }
    .status-secondary { background-color: #6c757d; }
    .status-danger { background-color: #dc3545; }
    .status-info { background-color: #17a2b8; }
    
    .side-buy { color: #28a745; font-weight: bold; }
    .side-sell { color: #dc3545; font-weight: bold; }
    
    .pagination {
        margin-top: 20px;
        display: flex;
        justify-content: center;
    }
    .alert {
        padding: 10px;
        border-radius: 8px;
        text-align: center;
        margin-bottom: 15px;
    }
    .no-orders {
        text-align: center !important;
        direction: rtl;
    }
    .alert-success { background: #d1e7dd; color: #0f5132; }
    .alert-danger { background: #f8d7da; color: #842029; }
    
    .stats-row {
        display: flex;
        justify-content: space-between;
        margin-bottom: 20px;
        gap: 15px;
    }
    .stat-card {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 8px;
        text-align: center;
        flex: 1;
        box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    }
    .stat-card h4 {
        margin: 0 0 10px 0;
        color: #6c757d;
        font-size: 14px;
    }
    .stat-card .value {
        font-size: 18px;
        font-weight: bold;
        color: #333;
    }
    
    .create-order-btn {
        background: var(--primary-color);
        color: white;
        padding: 10px 20px;
        border-radius: 8px;
        text-decoration: none;
        display: inline-block;
        margin-bottom: 20px;
        transition: background-color 0.3s;
    }
    .create-order-btn:hover {
        background: var(--primary-hover);
        color: white;
        text-decoration: none;
    }

    @media screen and (max-width: 768px) {
        .stats-row {
            flex-direction: column;
            gap: 10px;
        }
        table thead { display: none; }
        table tr {
            display: block;
            margin-bottom: 15px;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        table td {
            display: flex;
            justify-content: space-between;
            text-align: right;
            padding: 10px 15px;
            border: none;
            border-bottom: 1px solid #eee;
        }
        table td:last-child { border-bottom: 0; }
        table td::before {
            content: attr(data-label);
            font-weight: bold;
            padding-left: 10px;
            text-align: left;
        }
        .no-orders {
            display: block;
            width: 100%;
            padding: 15px 0;
            border: 0;
            box-shadow: none;
        }
    }
</style>
@endpush

@section('content')
<div class="container">
    <h2>سفارش‌های اسپات</h2>
    
    <a href="{{ route('spot.order.create.view') }}" class="create-order-btn">+ سفارش اسپات جدید</a>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <div class="stats-row">
        <div class="stat-card">
            <h4>کل سفارش‌ها</h4>
            <div class="value">{{ $orders->total() }}</div>
        </div>
        <div class="stat-card">
            <h4>در انتظار</h4>
            <div class="value">{{ $orders->where('status', 'New')->count() + $orders->where('status', 'PartiallyFilled')->count() }}</div>
        </div>
        <div class="stat-card">
            <h4>تکمیل شده</h4>
            <div class="value">{{ $orders->where('status', 'Filled')->count() }}</div>
        </div>
        <div class="stat-card">
            <h4>لغو شده</h4>
            <div class="value">{{ $orders->where('status', 'Cancelled')->count() + $orders->where('status', 'PartiallyFilledCanceled')->count() }}</div>
        </div>
    </div>

    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>جفت ارز</th>
                    <th>جهت</th>
                    <th>نوع سفارش</th>
                    <th>مقدار</th>
                    <th>قیمت</th>
                    <th>اجرا شده</th>
                    <th>وضعیت</th>
                    <th>زمان ایجاد</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($orders as $order)
                    <tr>
                        <td data-label="جفت ارز">{{ $order->symbol }}</td>
                        <td data-label="جهت">
                            <span class="{{ $order->side === 'Buy' ? 'side-buy' : 'side-sell' }}">
                                {{ $order->side === 'Buy' ? 'خرید' : 'فروش' }}
                            </span>
                        </td>
                        <td data-label="نوع سفارش">{{ $order->order_type === 'Market' ? 'بازار' : 'محدود' }}</td>
                        <td data-label="مقدار">{{ number_format($order->qty, 8) }} {{ $order->base_coin }}</td>
                        <td data-label="قیمت">
                            @if($order->price)
                                ${{ number_format($order->price, 4) }}
                            @else
                                بازار
                            @endif
                        </td>
                        <td data-label="اجرا شده">
                            {{ number_format($order->executed_qty, 8) }} {{ $order->base_coin }}
                            @if($order->executed_price)
                                <br><small>میانگین: ${{ number_format($order->executed_price, 4) }}</small>
                            @endif
                        </td>
                        <td data-label="وضعیت">
                            <span class="status-badge status-{{ $order->status_color }}">
                                {{ $order->status_display }}
                            </span>
                        </td>
                        <td data-label="زمان ایجاد">
                            {{ $order->created_at->format('Y/m/d H:i') }}
                            <br><small>{{ $order->created_at->diffForHumans() }}</small>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="no-orders">هیچ سفارش اسپاتی یافت نشد.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="pagination">
        {{ $orders->links() }}
    </div>
</div>
@endsection