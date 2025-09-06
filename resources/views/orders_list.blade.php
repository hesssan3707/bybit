@extends('layouts.app')

@section('title', 'Order History')

@push('styles')
<style>
    .container {
        padding: 20px;
        border-radius: 15px;
        box-shadow: 0 8px 25px rgba(0,0,0,0.1);
    }
    h2 {
        text-align: center;
        margin-bottom: 25px;
    }

    /* Mobile redirect buttons */
    .mobile-redirect-section {
        display: none;
        margin-bottom: 20px;
    }

    .redirect-buttons {
        display: flex;
        gap: 10px;
        margin-bottom: 20px;
    }

    .redirect-btn {
        flex: 1;
        padding: 15px;
        background: linear-gradient(135deg, var(--primary-color), var(--primary-hover));
        color: white;
        text-decoration: none;
        border-radius: 10px;
        text-align: center;
        font-weight: bold;
        transition: all 0.3s ease;
        box-shadow: 0 4px 15px rgba(0,123,255,0.3);
    }

    .redirect-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(0,123,255,0.4);
        color: white;
        text-decoration: none;
    }

    .redirect-btn.secondary {
        background: linear-gradient(135deg, #28a745, #20c997);
        box-shadow: 0 4px 15px rgba(40,167,69,0.3);
    }

    .redirect-btn.secondary:hover {
        box-shadow: 0 6px 20px rgba(40,167,69,0.4);
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
    .delete-btn {
        background-color: #dc3545;
        color: white;
        border: none;
        padding: 8px 12px;
        border-radius: 6px;
        cursor: pointer;
        transition: background-color 0.3s;
    }
    .delete-btn:hover {
        background-color: #c82333;
    }
    .close-btn {
        background-color: var(--primary-color);
        color: white;
        border: none;
        padding: 8px 12px;
        border-radius: 6px;
        cursor: pointer;
        transition: background-color 0.3s;
    }
    .close-btn:hover {
        background-color: var(--primary-hover);
    }
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

    @media screen and (max-width: 768px) {
        .mobile-redirect-section {
            display: block;
        }

        .redirect-buttons {
            flex-direction: column;
            gap: 15px;
        }

        .redirect-btn {
            padding: 18px;
            font-size: 16px;
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
<div class="glass-card container">
    <h2>تاریخچه معاملات</h2>

    @include('partials.exchange-access-check')

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

    <!-- Mobile redirect buttons (only visible on mobile) -->
    <div class="mobile-redirect-section">
        <div class="redirect-buttons">
            <a href="{{ route('orders.index') }}" class="redirect-btn">
                📊 سفارش‌های آتی
            </a>
            <a href="{{ route('pnl.history') }}" class="redirect-btn secondary">
                📈 سود و زیان
            </a>
        </div>
    </div>

    <!-- Orders Table -->
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>جهت</th>
                    <th>قیمت ورود</th>
                    <th>مقدار</th>
                    <th>SL / TP</th>
                    <th>وضعیت</th>
                    <th>عملیات</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($orders as $order)
                    <tr>
                        <td data-label="جهت">{{ $order->side }}</td>
                        <td data-label="قیمت ورود">{{ number_format($order->entry_price, 2) }}</td>
                        <td data-label="مقدار">{{ number_format($order->amount, 2) }}</td>
                        <td data-label="SL / TP">{{ number_format($order->tp, 2) }} / {{ number_format($order->sl, 2) }}</td>
                        <td data-label="وضعیت">{{ $order->status }}</td>
                        <td data-label="عملیات">
                            @if($order->status === 'pending')
                                <form action="{{ route('orders.destroy', $order) }}" method="POST" style="display:inline;" onsubmit="return confirm('آیا از لغو این سفارش مطمئن هستید؟');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="delete-btn">لغو کردن</button>
                                </form>
                            @elseif($order->status === 'filled')
                                <button type="button" class="close-btn" data-order-id="{{ $order->id }}" data-order-side="{{ $order->side }}">بستن</button>
                            @elseif($order->status === 'expired')
                                <form action="{{ route('orders.destroy', $order) }}" method="POST" style="display:inline;" onsubmit="return confirm('آیا از حذف این سفارش منقضی شده مطمئن هستید؟');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="delete-btn">حذف</button>
                                </form>
                            @else
                                -
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="no-orders">هیچ سفارشی یافت نشد.</td>
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

@push('scripts')
<script>
// Order closing functionality
document.addEventListener('DOMContentLoaded', function() {
    const closeButtons = document.querySelectorAll('.close-btn');

    closeButtons.forEach(button => {
        button.addEventListener('click', function() {
            const orderId = this.dataset.orderId;
            const orderSide = this.dataset.orderSide;
            const promptMessage = `Enter price distance (X) for this ${orderSide.toUpperCase()} position. The new closing price will be Market Price ${orderSide === 'buy' ? '+' : '-'} X.`;

            const priceDistance = prompt(promptMessage, '10');

            if (priceDistance !== null && !isNaN(priceDistance) && parseFloat(priceDistance) >= 0) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = `/orders/${orderId}/close`;

                const csrfToken = document.createElement('input');
                csrfToken.type = 'hidden';
                csrfToken.name = '_token';
                csrfToken.value = '{{ csrf_token() }}';
                form.appendChild(csrfToken);

                const distanceInput = document.createElement('input');
                distanceInput.type = 'hidden';
                distanceInput.name = 'price_distance';
                distanceInput.value = priceDistance;
                form.appendChild(distanceInput);

                document.body.appendChild(form);
                form.submit();
            } else if (priceDistance !== null) {
                alert('لطفا یک عدد صحیح و بزرگتر از صفر وارد کنید');
            }
        });
    });
});
</script>
@endpush
