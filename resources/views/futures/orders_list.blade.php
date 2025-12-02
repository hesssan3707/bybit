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

    /* Enforce new tabs by hiding any legacy mobile redirect buttons if present */
    .mobile-redirect-section,
    .redirect-buttons,
    .redirect-btn { display: none !important; }

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
        padding: 0 12px;
        border-radius: 6px;
        cursor: pointer;
        transition: background-color 0.3s;
        height: 34px; 
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
    .edit-btn {
        background-color: var(--primary-color);
        color: white;
        border: none;
        padding: 0 12px;
        border-radius: 6px;
        cursor: pointer;
        transition: background-color 0.3s;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        height: 34px; 
    }
    .edit-btn:hover {
        background-color: var(--primary-hover);
        color: white;
        text-decoration: none;
    }
    /* Icon-only button for viewing order chart */
    .icon-btn {
        background: linear-gradient(135deg, #6f42c1, #8c6df0); /* slight color shift to stand out */
        color: white;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        height: 34px;
        width: 34px;
        padding: 0;
        box-shadow: 0 4px 12px rgba(111,66,193,0.25);
    }
    .icon-btn:hover { 
        transform: translateY(-1px);
        box-shadow: 0 6px 16px rgba(111,66,193,0.35);
    }
    .icon-btn svg { width: 18px; height: 18px; }
    .order-row { transition: background-color 0.2s ease; cursor: pointer; }
    .order-row:hover { background-color: rgba(0, 123, 255, 0.08); }
    .row-highlight { background-color: rgba(0, 123, 255, 0.18) !important; box-shadow: inset 0 0 0 2px rgba(0,123,255,0.35); }

    /* Timeframe switcher styles */
    .tf-switch {
        position: absolute;
        top: 8px;
        right: 8px;
        display: flex;
        gap: 6px;
        background: rgba(255,255,255,0.9);
        border: 1px solid #eee;
        border-radius: 8px;
        padding: 4px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        z-index: 2;
    }
    .tf-switch .tf-item {
        font-size: 12px;
        color: #333;
        padding: 4px 8px;
        border-radius: 6px;
        cursor: pointer;
        user-select: none;
    }
    .tf-switch .tf-item:hover { background: #f1f3f5; }
    .tf-switch .tf-item.active {
        background: #6f42c1;
        color: #fff;
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

    /* Inline loader for chart overlay */
    .loader {
        width: 36px;
        height: 36px;
        border: 3px solid rgba(0,0,0,0.15);
        border-top-color: #6f42c1;
        border-radius: 50%;
        animation: spin 0.8s linear infinite;
    }
    @keyframes spin { to { transform: rotate(360deg); } }

    @media screen and (max-width: 768px) {
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

    @include('partials.mobile-tabs-futures')

    @php
        $symbols = isset($filterSymbols) && is_array($filterSymbols) && count($filterSymbols) > 0
            ? $filterSymbols
            : (collect($orders->items() ?? [])->pluck('symbol')->filter()->unique()->values()->all());
    @endphp

    @include('partials.filter-bar', [
        'action' => route('futures.orders'),
        'method' => 'GET',
        'from' => request('from'),
        'to' => request('to'),
        'symbol' => request('symbol'),
        'symbols' => $symbols,
        'hideSymbol' => (auth()->user()->future_strict_mode ?? false),
        'resetUrl' => route('futures.orders')
    ])

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
                    @php $answered = \App\Models\OrderStrategyFeedback::where('order_id', $order->id)->where('user_id', auth()->id())->exists(); @endphp
                    <tr class="order-row" data-exchange-order-id="{{ $order->order_id }}">
                        <td data-label="جهت">{{ $order->side }}</td>
                        <td data-label="قیمت ورود">{{ number_format($order->entry_price, 2) }}</td>
                        <td data-label="مقدار">{{ number_format($order->amount, 2) }}</td>
                        <td data-label="SL / TP">{{ number_format($order->tp, 2) }} / {{ number_format($order->sl, 2) }}</td>
                        <td data-label="وضعیت">{{ $order->status }}</td>
                        <td data-label="عملیات">
                            @if($order->status === 'pending')
                                <a href="{{ route('futures.order.edit', $order) }}" class="edit-btn" style="margin-left:8px">ویرایش</a>
                                <form action="{{ route('futures.orders.destroy', $order) }}" method="POST" style="display:inline;" class="modern-confirm-form" data-title="لغو سفارش آتی" data-message="آیا از لغو این سفارش مطمئن هستید؟">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="delete-btn">لغو کردن</button>
                                </form>
                            @elseif($order->status === 'filled')
                                <button type="button" class="icon-btn view-order-btn" data-order-id="{{ $order->id }}" title="نمایش نمودار سفارش" aria-label="نمایش نمودار سفارش" style="margin-left:8px">
                                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                        <path d="M4 19V5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                                        <path d="M20 19H4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                                        <path d="M7 15l4-4 3 3 5-6" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                                        <circle cx="7" cy="15" r="1.5" fill="currentColor"/>
                                        <circle cx="11" cy="11" r="1.5" fill="currentColor"/>
                                        <circle cx="14" cy="14" r="1.5" fill="currentColor"/>
                                        <circle cx="19" cy="8" r="1.5" fill="currentColor"/>
                                    </svg>
                                </button>
                                <button type="button" class="icon-btn highlight-pnl-btn" data-exchange-order-id="{{ $order->order_id }}" title="نمایش در سود و زیان" aria-label="نمایش در سود و زیان" style="margin-left:8px">
                                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                        <path d="M5 5h14v14H5z" stroke="currentColor" stroke-width="1.7"/>
                                        <path d="M7 9h10" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                                        <path d="M7 13h6" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                                    </svg>
                                </button>
                                @if(!$answered)
                                    <form action="{{ route('futures.orders.strategy_feedback', $order) }}" method="POST" style="display:inline;margin-right:8px">
                                        @csrf
                                        <span style="margin-left:6px">آیا این معامله با استراتژی شما همسو بود؟</span>
                                        <button name="answer" value="yes" type="submit" class="edit-btn" style="margin-left:6px">بله</button>
                                        <button name="answer" value="no" type="submit" class="delete-btn" style="margin-left:6px">خیر</button>
                                        <button name="answer" value="chart_no_load" type="submit" class="edit-btn" style="margin-left:6px">نمودار بارگذاری نشد</button>
                                        <button name="answer" value="no_strategy" type="submit" class="edit-btn" style="margin-left:6px">استراتژی ندارم</button>
                                    </form>
                                @endif
                            @elseif($order->status === 'expired')
                                @php
                                    $canResend = $order->closed_at && now()->diffInMinutes($order->closed_at) <= 30;
                                @endphp
                                @if($canResend)
                                    <form action="{{ route('futures.orders.resend', $order) }}" method="POST" style="display:inline;" class="modern-confirm-form" data-title="ارسال مجدد سفارش" data-message="آیا از ارسال مجدد این سفارش مطمئن هستید؟">
                                        @csrf
                                        <button type="submit" class="edit-btn" style="margin-left:8px">ارسال مجدد</button>
                                    </form>
                                @endif
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
    {{ $orders->appends(request()->except('page'))->links() }}
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

    var params = new URLSearchParams(window.location.search);
    var highlightOid = params.get('highlight_oid');
    if (highlightOid) {
        var row = document.querySelector('tr.order-row[data-exchange-order-id="' + highlightOid + '"]');
        if (row) {
            row.classList.add('row-highlight');
            row.scrollIntoView({ behavior: 'smooth', block: 'center' });
        } else {
            window.location.href = '{{ route('futures.pnl_history') }}' + '?order_not_found=1';
        }
    }

    document.querySelectorAll('.highlight-pnl-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var oid = this.getAttribute('data-exchange-order-id');
            window.location.href = '{{ route('futures.pnl_history') }}' + '?highlight_oid=' + encodeURIComponent(oid) + '&from=orders';
        });
    });
});
</script>
@endpush

@push('scripts')
    <!-- Lightweight Charts CDN -->
    <script src="https://unpkg.com/lightweight-charts@4.1.1/dist/lightweight-charts.standalone.production.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const backdrop = document.getElementById('orderChartBackdrop');
        const container = document.getElementById('order-chart-container');
        const tfPanel = document.getElementById('order-chart-tf');
        const closeBtn = document.getElementById('closeChartModalBtn');
        const loadingEl = document.getElementById('order-chart-loading');
        const TF_LIST = ['1m','5m','15m','1h','4h'];
        let currentChart = null;
        let isLoading = false;

        function renderNoData(message) {
            const msg = message || 'داده‌های مورد نیاز در دسترس نیست.';
            container.innerHTML = '';
            const box = document.createElement('div');
            box.style.cssText = 'height:100%;display:flex;align-items:center;justify-content:center;color:#6c757d;font-weight:600;font-size:14px;text-align:center;padding:16px;';
            box.textContent = msg;
            container.appendChild(box);
        }

        function openBackdrop() {
            backdrop.style.display = 'flex';
            setLoading(true);
        }
        function closeBackdrop() {
            backdrop.style.display = 'none';
            container.innerHTML = '';
            if (tfPanel) tfPanel.innerHTML = '';
            if (currentChart && typeof currentChart.remove === 'function') {
                try { currentChart.remove(); } catch (e) {}
            }
            currentChart = null;
            setLoading(false);
        }

        if (closeBtn) {
            closeBtn.addEventListener('click', closeBackdrop);
        }
        if (backdrop) {
            backdrop.addEventListener('click', function(e) {
                if (e.target === backdrop) { closeBackdrop(); }
            });
        }

        function setLoading(flag) {
            isLoading = !!flag;
            if (!loadingEl) return;
            loadingEl.style.display = isLoading ? 'flex' : 'none';
        }

        function renderChart(data) {
            // Initialize chart
            const chart = LightweightCharts.createChart(container, {
                height: 420,
                layout: { background: { type: 'solid', color: '#ffffff' }, textColor: '#333' },
                grid: { vertLines: { color: '#eee' }, horzLines: { color: '#eee' } },
                rightPriceScale: { borderVisible: false },
                timeScale: { borderVisible: false },
                localization: { locale: 'fa-IR' }
            });

            const series = chart.addCandlestickSeries();
            const candles = (data.candles || []).map(c => ({ time: c.time, open: c.open, high: c.high, low: c.low, close: c.close }));
            series.setData(candles);

            // Overlay price lines
            if (data.entry) {
                series.createPriceLine({ price: data.entry, color: '#1e90ff', lineWidth: 2, title: 'ورود' });
            }
            if (data.tp) {
                series.createPriceLine({ price: data.tp, color: '#20c997', lineWidth: 2, title: 'حد سود' });
            }
            if (data.sl) {
                series.createPriceLine({ price: data.sl, color: '#dc3545', lineWidth: 2, title: 'حد ضرر' });
            }
            if (data.exit) {
                series.createPriceLine({ price: data.exit, color: '#ffc107', lineWidth: 2, title: 'خروج' });
            }

            // Entry/Exit markers on candles if timestamps are available
            try {
                const tfMap = { '1m': 60, '5m': 300, '15m': 900, '1h': 3600, '4h': 14400 };
                const tfSec = tfMap[(data.timeframe || '15m')] || 900;
                const candleTimes = new Set(candles.map(c => c.time));
                const markers = [];

                if (data.filled_at) {
                    const entryTime = Math.floor((data.filled_at) / tfSec) * tfSec;
                    if (candleTimes.has(entryTime)) {
                        const buySide = (String(data.side || '').toLowerCase() === 'buy');
                        markers.push({
                            time: entryTime,
                            position: buySide ? 'belowBar' : 'aboveBar',
                            color: '#1e90ff',
                            shape: buySide ? 'arrowUp' : 'arrowDown',
                            text: 'ورود'
                        });
                    }
                }
                if (data.exit_at) {
                    const exitTime = Math.floor((data.exit_at) / tfSec) * tfSec;
                    if (candleTimes.has(exitTime)) {
                        markers.push({
                            time: exitTime,
                            position: 'aboveBar',
                            color: '#ffc107',
                            shape: 'arrowDown',
                            text: 'خروج'
                        });
                    }
                }
                if (markers.length) { series.setMarkers(markers); }
            } catch (e) { /* ignore marker failures */ }

            // Fit content
            chart.timeScale().fitContent();
            return chart;
        }

        function renderTfSwitch(activeTf, onSelect) {
            if (!tfPanel) return;
            tfPanel.innerHTML = '';
            TF_LIST.forEach(tf => {
                const el = document.createElement('div');
                el.className = 'tf-item' + (tf === activeTf ? ' active' : '');
                el.textContent = tf;
                el.addEventListener('click', () => onSelect(tf));
                tfPanel.appendChild(el);
            });
        }

        async function fetchChartData(orderId, tf) {
            const url = tf ? `/futures/orders/${orderId}/chart-data?tf=${encodeURIComponent(tf)}` : `/futures/orders/${orderId}/chart-data`;
            const resp = await fetch(url, { headers: { 'Accept': 'application/json' } });
            return await resp.json();
        }

        async function fetchAndRender(orderId, tf) {
            try {
                setLoading(true);
                const json = await fetchChartData(orderId, tf);
                if (!json.success) {
                    renderNoData(json.message);
                    setLoading(false);
                    return;
                }
                if (currentChart && typeof currentChart.remove === 'function') {
                    try { currentChart.remove(); } catch (e) {}
                }
                container.innerHTML = '';
                currentChart = renderChart(json.data || {});
                setLoading(false);
            } catch (e) {
                renderNoData('خطا در ارتباط با سرور');
                setLoading(false);
            }
        }

        // Attach click handlers
        document.querySelectorAll('.view-order-btn').forEach(btn => {
            btn.addEventListener('click', async function() {
                const id = this.dataset.orderId;
                openBackdrop();
                try {
                    setLoading(true);
                    const initial = await fetchChartData(id);
                    let activeTf = (initial.success && initial.data && initial.data.timeframe) ? initial.data.timeframe : '15m';
                    const onTfSelect = async (tf) => {
                        if (isLoading) return; // prevent concurrent fetches
                        activeTf = tf;
                        renderTfSwitch(activeTf, onTfSelect);
                        await fetchAndRender(id, tf);
                    };
                    renderTfSwitch(activeTf, onTfSelect);
                    if (!initial.success) {
                        renderNoData(initial.message);
                        setLoading(false);
                        return;
                    }
                    if (currentChart && typeof currentChart.remove === 'function') { try { currentChart.remove(); } catch (e) {} }
                    container.innerHTML = '';
                    currentChart = renderChart(initial.data || {});
                    setLoading(false);
                } catch (e) {
                    renderNoData('خطا در ارتباط با سرور');
                    setLoading(false);
                }
            });
        });
    });
    </script>
@endpush

<!-- Chart Modal Backdrop -->
<div id="orderChartBackdrop" style="position: fixed; inset: 0; background: rgba(0,0,0,0.5); display: none; align-items: center; justify-content: center; z-index: 1050;">
    <div style="background: #fff; color:#222; border-radius: 12px; width: 95%; max-width: 960px; padding: 12px; box-shadow: 0 12px 32px rgba(0,0,0,0.25);">
        <div style="display:flex; align-items:center; justify-content: space-between; margin-bottom: 8px;">
            <div style="font-weight:600;">نمایش سفارش</div>
            <button id="closeChartModalBtn" class="delete-btn" style="height:auto; padding:6px 10px;">بستن</button>
        </div>
        <div id="order-chart-wrapper" style="position:relative; height: 420px; width: 100%; overflow:hidden;">
            <div id="order-chart-container" style="height: 100%; width: 100%;"></div>
            <div id="order-chart-loading" style="position:absolute; inset:0; display:none; align-items:center; justify-content:center; background: rgba(255,255,255,0.85); z-index: 2;">
                <div class="loader" aria-label="Loading"></div>
            </div>
            <div id="order-chart-tf" class="tf-switch" aria-label="انتخاب تایم‌فریم"></div>
        </div>
    </div>
</div>

@include('partials.alert-modal')
    @if(request('pnl_not_found'))
        <div class="alert alert-danger">رکورد سود و زیان مرتبط یافت نشد.</div>
    @endif
