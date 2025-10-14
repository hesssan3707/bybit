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
        border: 1px solid rgba(222, 226, 230, 0.05);
        text-align: right;
    }
    thead {
        background-color: rgba(253, 253, 253, 0.05);
    }
    tbody tr:nth-of-type(odd) {
        background-color: rgba(249, 249, 249, 0.2);
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
            <a href="{{ route('futures.orders') }}" class="redirect-btn">
                📊 سفارش‌های آتی
            </a>
            <a href="{{ route('futures.pnl_history') }}" class="redirect-btn secondary">
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
                                <form action="{{ route('futures.orders.destroy', $order) }}" method="POST" style="display:inline;" class="modern-confirm-form" data-title="لغو سفارش آتی" data-message="آیا از لغو این سفارش مطمئن هستید؟">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="delete-btn">لغو کردن</button>
                                </form>
                            @elseif($order->status === 'filled')
                                {{-- دکمه بستن به بخش سود و زیان منتقل شد --}}
                                -
                            @elseif($order->status === 'expired')
                                <form action="{{ route('futures.orders.destroy', $order) }}" method="POST" style="display:inline;" class="modern-confirm-form" data-title="حذف سفارش منقضی" data-message="آیا از حذف این سفارش منقضی شده مطمئن هستید؟">
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
    {{ $orders->links() }}
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
            const confirmMessage = 'آیا مطمئن هستید که می‌خواهید این سفارش را با قیمت لحظه‌ای بازار ببندید؟';

            modernConfirm(
                'بستن سفارش',
                confirmMessage,
                function() {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = `/futures/orders/${orderId}/close`;

                    const csrfToken = document.createElement('input');
                    csrfToken.type = 'hidden';
                    csrfToken.name = '_token';
                    csrfToken.value = '{{ csrf_token() }}';
                    form.appendChild(csrfToken);

                    // No price_distance input is needed for market close

                    document.body.appendChild(form);
                    form.submit();
                }
            );
        });
    });

    // Intercept forms with modern confirm
    const confirmForms = document.querySelectorAll('.modern-confirm-form');
    confirmForms.forEach(function(form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const title = form.getAttribute('data-title') || 'تایید اقدام';
            const message = form.getAttribute('data-message') || 'آیا از انجام این عملیات مطمئن هستید؟';
            modernConfirm(title, message, function() { form.submit(); });
        });
    });
});
</script>
@endpush

@include('partials.alert-modal')
