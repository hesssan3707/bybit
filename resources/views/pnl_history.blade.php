@extends('layouts.app')

@section('title', 'P&L History')

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
    .pagination {
        margin-top: 20px;
        display: flex;
        justify-content: center;
    }
    .no-orders {
        text-align: center !important;
        direction: rtl;
    }
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
    <h2>تاریخچه سود و زیان</h2>

    @include('partials.exchange-access-check')

    @if(session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
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

    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>نماد</th>
                    <th>جهت</th>
                    <th>مقدار</th>
                    <th>میانگین قیمت ورود</th>
                    <th>میانگین قیمت خروج</th>
                    <th>سود و زیان</th>
                    <th>زمان بسته شدن</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($positions as $position)
                    <tr>
                        <td data-label="نماد">{{ $position->symbol }}</td>
                        <td data-label="جهت">{{ $position->side }}</td>
                        <td data-label="مقدار">{{ rtrim(rtrim(number_format($position->qty, 8), '0'), '.') }}</td>
                        <td data-label="میانگین قیمت ورود">{{ rtrim(rtrim(number_format($position->avg_entry_price, 4), '0'), '.') }}</td>
                        <td data-label="میانگین قیمت خروج">{{ rtrim(rtrim(number_format($position->avg_exit_price, 4), '0'), '.') }}</td>
                        <td data-label="سود و زیان">
                            <span style="color: {{ $position->pnl >= 0 ? 'green' : 'red' }};">
                                {{ rtrim(rtrim(number_format($position->pnl, 4), '0'), '.') }}
                            </span>
                        </td>
                        <td data-label="زمان بسته شدن">{{ \Carbon\Carbon::parse($position->closed_at)->format('Y-m-d H:i:s') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="no-orders">هیچ پوزیشنی یافت نشد.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="pagination">
        {{ $positions->links() }}
    </div>
</div>
@endsection
