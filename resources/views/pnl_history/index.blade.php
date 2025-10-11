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
        margin-top: 20px;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        background: white;
        border-radius: 10px;
        overflow: hidden;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }

    th, td {
        padding: 15px;
        text-align: right;
        border-bottom: 1px solid #eee;
    }

    th {
        background: linear-gradient(135deg, var(--primary-color), var(--primary-hover));
        color: white;
        font-weight: bold;
    }

    tr:hover {
        background-color: #f8f9fa;
    }

    .no-orders {
        text-align: center;
        color: #666;
        font-style: italic;
        padding: 40px;
    }

    .pagination {
        margin-top: 20px;
        display: flex;
        justify-content: center;
    }

    /* Mobile styles */
    @media (max-width: 768px) {
        .mobile-redirect-section {
            display: block;
        }

        .container {
            padding: 15px;
            margin: 10px;
        }

        table, thead, tbody, th, td, tr {
            display: block;
        }

        thead tr {
            position: absolute;
            top: -9999px;
            left: -9999px;
        }

        tr {
            border: 1px solid #ccc;
            margin-bottom: 10px;
            border-radius: 10px;
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        td {
            border: none;
            position: relative;
            padding-right: 50%;
            text-align: left;
        }

        td:before {
            content: attr(data-label) ": ";
            position: absolute;
            right: 6px;
            width: 45%;
            padding-right: 10px;
            white-space: nowrap;
            font-weight: bold;
            color: var(--primary-color);
        }

        .redirect-btn {
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
            <a href="{{ route('futures.orders') }}" class="redirect-btn">
                📊 سفارش‌های آتی
            </a>
            <a href="{{ route('futures.pnl_history') }}" class="redirect-btn secondary">
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
                @forelse ($trades as $trade)
                    <tr>
                        <td data-label="نماد">{{ $trade->symbol }}</td>
                        <td data-label="جهت">{{ $trade->side }}</td>
                        <td data-label="مقدار">{{ rtrim(rtrim(number_format($trade->qty, 8), '0'), '.') }}</td>
                        <td data-label="میانگین قیمت ورود">{{ rtrim(rtrim(number_format($trade->avg_entry_price, 4), '0'), '.') }}</td>
                        <td data-label="میانگین قیمت خروج">{{ rtrim(rtrim(number_format($trade->avg_exit_price, 4), '0'), '.') }}</td>
                        <td data-label="سود و زیان">
                            <span style="color: {{ $trade->pnl >= 0 ? 'green' : 'red' }};">
                                {{ rtrim(rtrim(number_format($trade->pnl, 4), '0'), '.') }}
                            </span>
                        </td>
                        <td data-label="زمان بسته شدن">{{ \Carbon\Carbon::parse($trade->closed_at)->format('Y-m-d H:i:s') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="no-orders">هیچ معامله‌ای یافت نشد.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $trades->links() }}
</div>
@endsection