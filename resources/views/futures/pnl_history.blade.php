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
        border: 1px solid rgba(222, 226, 230, 0.05);
        text-align: right;
    }
    thead {
        background-color: rgba(253, 253, 253, 0.05);
    }
    tbody tr:nth-of-type(odd) {
        background-color: rgba(249, 249, 249, 0.2);
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

    /* Close button style */
    .close-btn {
        background: linear-gradient(135deg, #dc3545, #c82333);
        color: #fff;
        border: none;
        border-radius: 8px;
        padding: 8px 14px;
        cursor: pointer;
        box-shadow: 0 4px 12px rgba(220,53,69,0.35);
        transition: all 0.25s ease;
        font-weight: 600;
    }
    .close-btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 6px 16px rgba(220,53,69,0.5);
    }


    /* Open positions card layout (mobile-first) */
    .open-positions-table { display: block; }

    .position-card {
        background: rgba(255,255,255,0.06);
        backdrop-filter: blur(4px);
        border: 1px solid rgba(255,255,255,0.08);
        border-radius: 12px;
        padding: 12px 14px;
        box-shadow: 0 6px 18px rgba(0,0,0,0.08);
    }
    .position-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 8px;
        font-weight: 700;
    }
    .position-meta {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 8px 14px;
        margin-bottom: 10px;
    }
    .position-field {
        display: flex;
        justify-content: space-between;
    }
    .position-label { color: #cbd5e0; font-weight: 600; }
    .position-value { color: #fff; }
    .pnl-positive { color: #28a745; font-weight: 700; }
    .pnl-negative { color: #dc3545; font-weight: 700; }
    /* Badge styles for readable PnL values */
    .pnl-badge { display: inline-block; padding: 4px 10px; border-radius: 999px; font-weight: 700; font-size: 0.95em; }
    .badge-positive { background: rgba(251, 251, 251, 0.85); color: #28a745; border: 1px solid rgba(40,167,69,0.35); }
    .badge-negative { background: rgba(0, 0, 0, 0.7); color: #dc3545; border: 1px solid rgba(220,53,69,0.35); }
    .sync-warning { display: inline-block; margin-right: 6px; color: #ffcc00; font-weight: 900; cursor: help; }
    @media screen and (max-width: 768px) {
      .pnl-badge { font-size: 1.05em; padding: 6px 12px; }
    }
    @media screen and (max-width: 768px) {
        /* Turn tables into cards on mobile */
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

        /* Open positions: show cards, hide table */
        .position-card { font-size: 0.95rem; }
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
    @if($errors->any())
        <div class="alert alert-danger">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    @include('partials.mobile-tabs-futures')

    @php
        $symbols = isset($filterSymbols) && is_array($filterSymbols) && count($filterSymbols) > 0
            ? $filterSymbols
            : (collect($openTrades ?? [])->pluck('symbol')
                ->merge(collect(($closedTrades ?? null) && method_exists($closedTrades, 'items') ? $closedTrades->items() : ($closedTrades ?? []))->pluck('symbol'))
                ->filter()->unique()->values()->all());
    @endphp

    @include('partials.filter-bar', [
        'action' => route('futures.pnl_history'),
        'method' => 'GET',
        'from' => request('from'),
        'to' => request('to'),
        'symbol' => request('symbol'),
        'symbols' => $symbols,
        'hideSymbol' => (auth()->user()->future_strict_mode ?? false),
        'resetUrl' => route('futures.pnl_history')
    ])

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
                @foreach ($openTrades as $trade)
                    <tr>
                        <td data-label="نماد">{{ $trade->symbol }}</td>
                        <td data-label="جهت">{{ $trade->side }}</td>
                        <td data-label="مقدار">{{ rtrim(rtrim(number_format($trade->qty, 4), '0'), '.') }}</td>
                        <td data-label="میانگین قیمت ورود">{{ rtrim(rtrim(number_format($trade->avg_entry_price, 2), '0'), '.') }}</td>
                        <td data-label="میانگین قیمت خروج">--</td>
                        <td data-label="سود و زیان">
                            <span style="direction: ltr;" class="pnl-badge {{ $trade->pnl >= 0 ? 'badge-positive' : 'badge-negative' }}">
                                {{ rtrim(rtrim(number_format($trade->pnl, 2), '0'), '.') }}
                            </span>
                        </td>
                        <td data-label="زمان بسته شدن">
                            @php $oid = $orderModelByOrderId[$trade->order_id] ?? null; @endphp
                            @if($oid)
                                <form action="{{ url('/futures/orders/' . $oid . '/close') }}" method="POST" style="display:inline;" class="close-position-form" title="بستن موقعیت باز">
                                    @csrf
                                    <button type="submit" class="close-btn">بستن</button>
                                </form>
                            @else
                                <span class="text-muted" title="سفارش مرتبط برای بستن یافت نشد">سفارش مرتبط یافت نشد</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
                @if(isset($openTrades[0]))
                    <tr><td colspan="7" class="section-separator"></td></tr>
                @endif
                @forelse ($closedTrades as $trade)
                    <tr>
                        <td data-label="نماد">{{ $trade->symbol }}</td>
                        <td data-label="جهت">{{ $trade->side }}</td>
                        <td data-label="مقدار">{{ rtrim(rtrim(number_format($trade->qty, 8), '0'), '.') }}</td>
                        <td data-label="میانگین قیمت ورود">{{ rtrim(rtrim(number_format($trade->avg_entry_price, 2), '0'), '.') }}</td>
                        <td data-label="میانگین قیمت خروج">{{ rtrim(rtrim(number_format($trade->avg_exit_price, 2), '0'), '.') }}</td>
                        <td data-label="سود و زیان">
                            @if(($trade->synchronized ?? 0) === 2)
                                <span class="sync-warning" title="این عدد ممکن است اشتباه باشد">!</span>
                            @endif
                            <span style="direction: ltr;" class="pnl-badge {{ $trade->pnl >= 0 ? 'badge-positive' : 'badge-negative' }}">
                                {{ rtrim(rtrim(number_format($trade->pnl, 2), '0'), '.') }}
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

    {{ $closedTrades->appends(request()->except('page'))->links() }}
    <form id="closeAllForm" action="{{ route('futures.orders.close_all') }}" method="POST" style="display:none;">
        @csrf
    </form>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const forms = document.querySelectorAll('.close-position-form');
    const openCount = {{ count($openTrades) }};
    const manualCloseBanActive = {{ $manualCloseBanActive ? 'true' : 'false' }};
    const strictModeActive = {{ $strictModeActive ? 'true' : 'false' }};
    const manualCloseBanRemainingFa = @json($manualCloseBanRemainingFa);
    forms.forEach(function(form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            if (strictModeActive && manualCloseBanActive) {
                const msg = manualCloseBanRemainingFa
                    ? ('شما مجاز به بستن دستی موقعیت نیستید. لطفاً ' + manualCloseBanRemainingFa + ' صبر کنید.')
                    : 'شما مجاز به بستن دستی موقعیت نیستید.';
                showAlertModal({
                    title: 'خطا',
                    message: msg,
                    type: 'error',
                    confirmText: 'متوجه شدم',
                    showCancel: false
                });
                return;
            }

            if (openCount > 1) {
                showAlertModal({
                    title: 'بستن موقعیت',
                    message: 'کدام اقدام را می‌خواهید انجام دهید؟',
                    type: 'warning',
                    confirmText: 'بستن پوزیشن فعلی',
                    cancelText: 'انصراف',
                    showCancel: true,
                    secondaryConfirmText: 'بستن تمام پوزیشن ها',
                    showSecondaryConfirm: true,
                    onConfirm: function() { form.submit(); },
                    onSecondaryConfirm: function() { document.getElementById('closeAllForm').submit(); }
                });
            } else {
                modernConfirm(
                    'بستن موقعیت',
                    'آیا از بستن این موقعیت مطمئن هستید؟',
                    function() { form.submit(); }
                );
            }
        });
    });
});
</script>
@endpush

@include('partials.alert-modal')
