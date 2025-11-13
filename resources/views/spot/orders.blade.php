@extends('layouts.app')

@section('body-class', 'spot-page')

@section('title', 'Spot Orders')

@push('styles')
<style>
    .container {
        background: rgba(255,255,255,0.08);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        padding: 20px;
        border-radius: 15px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.25);
        animation: fadeIn 0.5s ease-out;
    }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(6px);} to { opacity: 1; transform: translateY(0);} }
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
        border: 1px solid rgba(222, 226, 230, 0.05);
        text-align: right;
    }
    thead {
        background-color: rgba(253, 253, 253, 0.05);
    }
    tbody tr:nth-of-type(odd) {
        background-color: rgba(249, 249, 249, 0.2);
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
        padding: 15px;
        border-radius: 8px;
        text-align: center;
        flex: 1;
        box-shadow: 0 2px 5px rgba(0,0,0,0.2);
    }
    .stat-card h4 {
        margin: 0 0 10px 0;
        color: #f4f5f6;
        font-size: 14px;
    }
    .stat-card .value {
        font-size: 18px;
        font-weight: bold;
        color: #efeeee;
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

    .cancel-btn {
        background: #dc3545;
        color: white;
        padding: 4px 8px;
        border: none;
        border-radius: 4px;
        font-size: 12px;
        cursor: pointer;
        transition: background-color 0.3s;
        text-decoration: none;
        display: inline-block;
    }

    .cancel-btn:hover {
        background: #c82333;
        color: white;
        text-decoration: none;
    }

    .cancel-btn:disabled {
        background: #6c757d;
        cursor: not-allowed;
    }

    .action-buttons {
        display: flex;
        gap: 5px;
        flex-wrap: wrap;
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
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        table td {
            display: flex;
            justify-content: space-between;
            text-align: right;
            padding: 10px 15px;
            border: none;
            border-bottom: 1px solid rgba(238, 238, 238, 0.25);
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

    @include('partials.exchange-access-check')

    {{-- Show create order button only if user has proper access --}}
    @php
        $exchangeAccess = request()->attributes->get('exchange_access');
        $accessRestricted = request()->attributes->get('access_restricted', false);
        $hasExchangeAccess = $exchangeAccess && $exchangeAccess['current_exchange'] && !$accessRestricted;
    @endphp

    @if($hasExchangeAccess)
        <a href="{{ route('spot.order.create.view') }}" class="create-order-btn">+ سفارش اسپات جدید</a>
    @endif

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

    @php
        $symbols = isset($filterSymbols) && is_array($filterSymbols) && count($filterSymbols) > 0
            ? $filterSymbols
            : (collect($orders->items() ?? [])->pluck('symbol')->filter()->unique()->values()->all());
    @endphp

    @include('partials.filter-bar', [
        'action' => route('spot.orders.view'),
        'method' => 'GET',
        'from' => request('from'),
        'to' => request('to'),
        'symbol' => request('symbol'),
        'symbols' => $symbols,
        'resetUrl' => route('spot.orders.view')
    ])

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
            <div class="value">{{
                ($orders->where('status', 'cancelled')->count() +
                 $orders->where('status', 'Rejected')->count() )
            }}</div>
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
                    <th>عملیات</th>
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
                        <td data-label="مقدار"><span style="direction:ltr;">{{ number_format($order->qty, 8) }} {{ $order->base_coin }}</span></td>
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
                            <span style="direction:ltr;">{{ $order->created_at->format('Y/m/d H:i') }}</span>
                            <br><small>{{ $order->created_at->diffForHumans() }}</small>
                        </td>
                        <td data-label="عملیات">
                            <div class="action-buttons">
                                @if(in_array($order->status, ['New', 'PartiallyFilled']) && $order->order_id)
                                    <form method="POST" action="{{ route('spot.order.cancel.web') }}" style="display: inline;" class="cancel-spot-order-form">
                                        @csrf
                                        <input type="hidden" name="orderId" value="{{ $order->order_id }}">
                                        <input type="hidden" name="symbol" value="{{ $order->symbol }}">
                                        <button type="submit" class="cancel-btn">لغو</button>
                                    </form>
                                @else
                                    <span style="color:rgb(255, 255, 255); font-size: 12px;">
                                        @if($order->status === 'Filled')
                                            تکمیل شده
                                        @elseif($order->status === 'Cancelled')
                                            لغو شده
                                        @elseif($order->status === 'PartiallyFilledCanceled')
                                            نیمه لغو
                                        @else
                                            -
                                        @endif
                                    </span>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="no-orders">هیچ سفارش اسپاتی یافت نشد.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $orders->appends(request()->except('page'))->links() }}
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const forms = document.querySelectorAll('.cancel-spot-order-form');
    forms.forEach(function(form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            modernConfirm(
                'لغو سفارش اسپات',
                'آیا مطمئن هستید که می‌خواهید این سفارش را لغو کنید؟',
                function() { form.submit(); }
            );
        });
    });
});
</script>
@endpush

@include('partials.alert-modal')
