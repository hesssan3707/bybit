@extends('layouts.app')

@section('title', 'ژورنال معاملاتی')

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
    .filters {
        display: flex;
        gap: 15px;
        margin-bottom: 25px;
        justify-content: center;
        align-items: center;
    }
    .filters .form-control, .filters .btn {
        background-color: rgba(255, 255, 255, 0.1);
        color: #fff;
        border: 1px solid rgba(255, 255, 255, 0.2);
        border-radius: 8px;
    }
    .filters .form-control:focus {
        background-color: rgba(255, 255, 255, 0.2);
        color: #fff;
        border-color: var(--primary-color);
        box-shadow: none;
    }
    .form-control option
    {
        color:black;
    }
    .filters .btn-primary {
        background-color: var(--primary-color);
        border-color: var(--primary-color);
        transition: background-color 0.3s;
    }
    .filters .btn-primary:hover {
        background-color: var(--primary-hover);
    }
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    .stat-card {
        background: rgba(255, 255, 255, 0.05);
        padding: 20px;
        border-radius: 10px;
        text-align: center;
    }
    .stat-card h4 {
        margin-bottom: 10px;
        color: #adb5bd;
    }
    .stat-card p {
        font-size: 1.5rem;
        font-weight: bold;
    }
    .pnl-positive { color: #28a745; }
    .pnl-negative { color: #dc3545; }
    .chart-container {
        background: rgba(255, 255, 255, 0.05);
        padding: 20px;
        border-radius: 10px;
        margin-bottom: 30px;
        overflow-x: hidden;
    }
    /* (Old mobile redirect buttons removed; now using compact tabs partial) */
    /* Badges for period state */
    .badge { display:inline-block; padding:4px 8px; border-radius:999px; font-size:12px; margin-inline-start:8px; }
    .badge-active { background: #ffffff; color:#111; border: 1px solid rgba(0,0,0,0.25); }
    .badge-ended { background: #000000; color:#ffffff; border: 1px solid rgba(255,255,255,0.25); }
    .badge-default { background: rgba(0,123,255,0.8); color:#0d6efd; border:1px solid rgba(0,123,255,0.35); }
    /* Modern CTA button for starting period */
    .btn-start-period {
        background: linear-gradient(135deg, #007bff, #0056b3);
        color: #fff;
        border: none;
        border-radius: 12px;
        padding: 10px 18px;
        font-weight: 600;
        box-shadow: 0 4px 15px rgba(0, 123, 255, 0.3);
        transition: all 0.2s ease;
    }
    .btn-start-period:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(0, 123, 255, 0.4);
        color: #fff;
    }

    /* Modern input styling for modal field */
    .modern-input {
        background: rgba(255, 255, 255, 0.12);
        color: #111; /* improved readability */
        border: 1px solid rgba(255, 255, 255, 0.25);
        border-radius: 12px;
        padding: 12px 14px;
        outline: none;
        transition: border-color 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
    }
    .modern-input::placeholder { color: rgba(0,0,0,0.5); }
    .modern-input:focus {
        border-color: rgba(0, 123, 255, 0.6);
        box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.25);
        background: rgba(255,255,255,0.16);
    }
    .modern-input.input-error {
        border-color: rgba(220,53,69,0.65);
        box-shadow: 0 0 0 3px rgba(220,53,69,0.2);
    }
    .alert-modal-footer .btn[disabled] { opacity: 0.6; cursor: not-allowed; }
    /* Improved end period button style */
    .btn-end-period {
        background: linear-gradient(135deg, #ef4444, #dc2626);
        color: #fff;
        border: none;
        border-radius: 10px;
        padding: 8px 14px;
        font-weight: 600;
        box-shadow: 0 6px 20px rgba(239,68,68,0.35);
        transition: all 0.2s ease;
    }
    .btn-end-period:hover {
        transform: translateY(-1px);
        box-shadow: 0 8px 24px rgba(239,68,68,0.45);
        color: #fff;
    }

    /* Clickable period chips */
    .period-chip {
        cursor: pointer;
        transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease;
        border: 1px solid rgba(255, 255, 255, 0.15);
    }
    .period-chip:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 24px rgba(0,0,0,0.25);
        border-color: rgba(0, 123, 255, 0.35);
    }
    .period-chip.selected {
        border-color: rgba(0, 123, 255, 0.55);
        box-shadow: 0 12px 28px rgba(0, 123, 255, 0.25);
    }
    /* Glassy overlay for this page's start period modal */
    #startPeriodModal.alert-modal-overlay {
        background: rgba(255, 255, 255, 0.08);
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
    }
    /* Prevent background scrolling when modal is open */
    body.modal-open { overflow: hidden; }
    
    @media (max-width: 768px) {
        .filters {
            flex-direction: column;
        }
        .stats-grid {
            grid-template-columns: 1fr;
        }
        /* Compact tabs partial handles mobile navigation */
        .modal-content { max-width: 92vw; }
        /* Mobile: fixed same-size width for period chips and glassy filters box */
        .period-chip { width: 100%; }
        .filters {
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.18);
            padding: 12px;
            border-radius: 12px;
        }
    }
</style>
@endpush

@section('content')
<div class="glass-card container">
    <h2>ژورنال معاملاتی</h2>

    @include('partials.mobile-tabs-futures')

    @php
        $recentPeriods = $periods->sortByDesc(function($p){ return $p->ended_at ?? $p->started_at; })->take(10);
    @endphp

    @php
        $extraHtml = view('partials.journal-extra-filters', compact('recentPeriods', 'selectedPeriod', 'side', 'exchangeOptions', 'userExchangeId'))->render();
    @endphp
    @include('partials.filter-bar', [
        'action' => route('futures.journal'),
        'method' => 'GET',
        'hideDate' => true,
        'hideSymbol' => true,
        'extraHtml' => $extraHtml,
        'resetUrl' => route('futures.journal')
    ])

    <!-- Periods Management Section -->
    <!-- Periods Management Section -->
    <div style="margin-bottom: 25px; padding: 20px; border-radius: 16px; background: rgba(255, 255, 255, 0.06); border: 1px solid rgba(255,255,255,0.05);">
        
        <!-- Header: Title & Actions -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <div style="width: 40px; height: 40px; background: rgba(255,255,255,0.1); border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                    <i class="fas fa-history" style=" font-size: 1.2em;"></i>
                </div>
                <h3 style="margin: 0; font-size: 1.2em; font-weight: 600;">دوره‌های معاملاتی</h3>
            </div>
            
            <div style="display: flex; gap: 10px;">
                <button type="button" class="btn btn-start-period" onclick="openStartPeriodModal()" title="شروع دوره جدید" 
                    style="width: 42px; height: 42px; padding: 0; display: flex; align-items: center; justify-content: center; border-radius: 12px; background: var(--primary-color, #ffc107); color: #000; border: none; transition: transform 0.2s;">
                    <i class="fas fa-plus" style="font-size: 16px;"></i>
                </button>
                
                <form method="POST" action="{{ route('futures.periods.recompute_all') }}" id="recomputeAllForm" style="margin: 0;">
                    @csrf
                    <button type="submit" class="btn btn-start-period" id="recomputeAllBtn" title="بروزرسانی همه دوره‌ها" 
                        style="width: 42px; height: 42px; padding: 0; display: flex; align-items: center; justify-content: center; border-radius: 12px; background: rgba(255,255,255,0.1); color: #fff; border: 1px solid rgba(255,255,255,0.1); transition: all 0.2s;">
                        <i class="fas fa-sync" style="font-size: 16px;"></i>
                    </button>
                </form>
            </div>
        </div>

        <!-- Messages -->
        @if(session('success'))
            <div class="alert alert-success" style="margin-bottom: 20px; border-radius: 10px; padding: 10px 15px; font-size: 0.9em;">
                <i class="fas fa-check-circle"></i> {!! session('success') !!}
            </div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger" style="margin-bottom: 20px; border-radius: 10px; padding: 10px 15px; font-size: 0.9em;">
                <i class="fas fa-exclamation-circle"></i> {{ session('error') }}
            </div>
        @endif

        <!-- Periods Grid -->
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 15px;">
            @foreach($recentPeriods->take(6) as $per)
                <div class="period-card {{ ($selectedPeriod && $selectedPeriod->id === $per->id) ? 'selected' : '' }}" 
                     onclick="if(event.target.closest('form') || event.target.closest('button')) return; selectPeriod({{ $per->id }})"
                     style="background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255,255,255,0.08); border-radius: 12px; padding: 15px; cursor: pointer; transition: all 0.2s; position: relative; overflow: hidden;">
                    
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px;">
                        <div style="font-weight: 600; color: #fff; font-size: 1.05em;">{{ $per->name }}</div>
                        <div>
                            @if($per->is_active)
                                <span class="badge-status-active">فعال</span>
                            @else
                                <span class="badge-status-ended">پایان‌یافته</span>
                            @endif
                        </div>
                    </div>

                    <div style="font-size: 0.85em; color: #aaa; margin-bottom: 15px; display: flex; align-items: center; gap: 8px;">
                        <i class="far fa-calendar"></i>
                        <span>{{ optional($per->started_at)->format('Y-m-d') }}</span>
                        <i class="fas fa-arrow-left" style="font-size: 0.8em; opacity: 0.5;"></i>
                        <span>{{ $per->ended_at ? $per->ended_at->format('Y-m-d') : 'اکنون' }}</span>
                    </div>

                    @if($per->is_active && !$per->is_default)
                        <div style="border-top: 1px solid rgba(255,255,255,0.1); padding-top: 12px; display: flex; justify-content: flex-end;">
                            <form method="POST" action="{{ route('futures.periods.end', ['period' => $per->id]) }}">
                                @csrf
                                <button type="submit" class="btn-end-period-action">
                                    <i class="fas fa-stop-circle"></i> پایان دوره
                                </button>
                            </form>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
        
        <style>
            /* Badges */
            .badge-status-active {
                background: rgba(255, 255, 255, 0.8);
                color: #28a745;
                border: 1px solid rgba(40, 167, 69, 0.3);
                padding: 4px 10px;
                border-radius: 8px;
                font-size: 0.8em;
                font-weight: 500;
            }
            .badge-status-ended {
                background: rgba(0, 0, 0, 0.8);
                color: #dc3545;
                border: 1px solid rgba(220, 53, 69, 0.3);
                padding: 4px 10px;
                border-radius: 8px;
                font-size: 0.8em;
                font-weight: 500;
            }

            /* End Period Button */
            .btn-end-period-action {
                background: rgba(0, 0, 0, 0.3);
                border: 1px solid rgba(220, 53, 69, 0.3);
                color: #dc3545;
                font-size: 0.85em;
                cursor: pointer;
                padding: 8px 14px;
                border-radius: 8px;
                display: flex;
                align-items: center;
                gap: 8px;
                transition: all 0.2s;
            }
            .btn-end-period-action:hover {
                background: rgba(220, 53, 69, 0.8);
                color: #fff;
                border-color: rgba(220, 53, 69, 0.8);
            }

            /* Period Card */
            .period-card:hover {
                background: rgba(255, 255, 255, 0.08) !important;
                transform: translateY(-2px);
            }
            .period-card.selected {
                background: rgba(255, 255, 255, 0.12) !important;
                border: 1px solid rgba(255, 255, 255, 0.4) !important;
                box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
                transform: scale(1.02);
            }
        </style>
    </div>

    <!-- Start Period Modal - using modern alert modal styles -->
    <div id="startPeriodModal" class="alert-modal-overlay" style="display: none;">
        <div class="alert-modal-container">
            <div class="alert-modal-content">
                <div class="alert-modal-header">
                    <div class="alert-modal-icon success">
                        <i class="fas fa-hourglass-start"></i>
                    </div>
                    <h3>شروع دوره جدید</h3>
                    <button class="alert-modal-close" type="button" aria-label="بستن" onclick="closeStartPeriodModal()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <form id="startPeriodForm" method="POST" action="{{ route('futures.periods.start') }}" onsubmit="return validateStartPeriodForm(this)">
                    @csrf
                    <div class="alert-modal-body">
                        <div style="display:flex; flex-direction:column; gap:10px; text-align:start;">
                            <label for="periodName" style="font-weight:600; color:#333;">نام دوره </label>
                            <input id="periodName" type="text" name="name" class="form-control modern-input" placeholder="مثلاً: فصل پاییز یا کمپین Q3" required />
                        </div>
                    </div>
                    <div class="alert-modal-footer">
                        <button type="button" class="btn btn-secondary" onclick="closeStartPeriodModal()">انصراف</button>
                        <button type="submit" class="btn btn-primary">شروع</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="stats-grid">
        <!-- Row 1: PNL -->
        <div class="stat-card">
            <h4>کل سود/ضرر</h4>
            <p class="{{ $totalPnl >= 0 ? 'pnl-positive' : 'pnl-negative' }}" style="direction:ltr">${{ number_format($totalPnl, 2) }}</p>
        </div>
        <div class="stat-card">
            <h4>کل سود</h4>
            <p class="pnl-positive" style="direction:ltr">${{ number_format($totalProfits, 2) }}</p>
        </div>
        <div class="stat-card">
            <h4>کل ضرر</h4>
            <p class="pnl-negative" style="direction:ltr">${{ number_format($totalLosses, 2) }}</p>
        </div>
        <div class="stat-card">
            <h4>بزرگترین سود</h4>
            <p class="pnl-positive" style="direction:ltr">${{ number_format($biggestProfit, 2) }}</p>
        </div>
        <div class="stat-card">
            <h4>بزرگترین ضرر</h4>
            <p class="pnl-negative" style="direction:ltr">${{ number_format($biggestLoss, 2) }}</p>
        </div>
        <div class="stat-card">
            <h4>کل سود/ضرر ٪</h4>
            <p class="{{ $totalPnlPercent >= 0 ? 'pnl-positive' : 'pnl-negative' }}" style="direction:ltr">{{ number_format($totalPnlPercent, 2) }}%</p>
        </div>
        <div class="stat-card">
            <h4>کل سود ٪</h4>
            <p class="pnl-positive" style="direction:ltr">{{ number_format($totalProfitPercent, 2) }}%</p>
        </div>
        <div class="stat-card">
            <h4>کل ضرر ٪</h4>
            <p class="pnl-negative" style="direction:ltr">{{ number_format($totalLossPercent, 2) }}%</p>
        </div>
        <div class="stat-card">
            <h4>رتبه شما (دلاری)</h4>
            <p>{{ $pnlRank ?? 'N/A' }}</p>
        </div>
        <div class="stat-card">
            <h4>رتبه شما (درصد)</h4>
            <p>{{ $pnlPercentRank ?? 'N/A' }}</p>
        </div>
        <div class="stat-card">
            <h4>تعداد معامله</h4>
            <p>{{ $totalTrades }}</p>
        </div>
        <div class="stat-card">
            <h4>تعداد معامله سود</h4>
            <p class="pnl-positive">{{ $profitableTradesCount }}</p>
        </div>
        <div class="stat-card">
            <h4>تعداد معامله ضرر</h4>
            <p class="pnl-negative">{{ $losingTradesCount }}</p>
        </div>
        <div class="stat-card">
            <h4>متوسط ریسک %</h4>
            <p class="pnl-negative" style="direction:ltr">{{ number_format($averageRisk, 2) }}%</p>
        </div>
        <div class="stat-card">
            <h4>متوسط ریسک به ریوارد</h4>
            <p>1 : {{ number_format($averageRRR, 2) }}</p>
        </div>
    </div>

    <div class="chart-container">
        <div id="pnlChart"></div>
    </div>
    <div class="chart-container">
        <div id="cumulativePnlChart"></div>
    </div>
     <div class="chart-container">
        <div id="cumulativePnlPercentChart"></div>
    </div>

    <div class="text-center text-muted mt-4">
        <p>این صفحه فقط معامله هایی که از طریق این سایت ثبت شده اند و اطلاعات معامله با صرافی سینک شده است را محاسبه میکند</p>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Client-side cooldown to prevent repeated presses within 10 minutes
        (function(){
            const btn = document.getElementById('recomputeAllBtn');
            const form = document.getElementById('recomputeAllForm');
            if (!btn || !form) return;

            const userId = '{{ auth()->id() }}';
            const accType = '{{ ($selectedPeriod && $selectedPeriod->is_demo) ? 'demo' : 'real' }}';
            const key = `journalRecomputeCooldown:${userId}:${accType}`;

            function isCooldownActive() {
                const until = localStorage.getItem(key);
                if (!until) return false;
                const ts = parseInt(until, 10);
                return !isNaN(ts) && Date.now() < ts;
            }
            function updateBtnState() {
                if (isCooldownActive()) {
                    btn.style.display = 'none'; // Hide completely
                } else {
                    btn.style.display = 'flex'; // Show as flex
                    btn.disabled = false;
                }
            }
            updateBtnState();
            setInterval(updateBtnState, 5000);
            form.addEventListener('submit', function(e) {
                if (isCooldownActive()) {
                    e.preventDefault();
                    alert('این عملیات اخیراً انجام شده است. لطفاً پس از ۱۵ دقیقه دوباره تلاش کنید. در صورت تداوم مشکل به ادمین اطلاع دهید.');
                    return false;
                }
                const tenMinutesMs = 10 * 60 * 1000;
                localStorage.setItem(key, String(Date.now() + tenMinutesMs));
                btn.style.display = 'none'; // Hide immediately on submit
                setTimeout(updateBtnState, 1000); // Check again after a second
            });
        })();

        // Report Journal Issue (AJAX create ticket, prevent duplicate)
        (function(){
            const reportLink = document.getElementById('reportJournalIssue');
            if (!reportLink) return;
            function getCsrf(){
                const meta = document.querySelector('meta[name="csrf-token"]');
                if (meta) return meta.getAttribute('content');
                const tokenInput = document.querySelector('#recomputeAllForm input[name="_token"]');
                return tokenInput ? tokenInput.value : '';
            }
            reportLink.addEventListener('click', function(e){
                e.preventDefault();
                const csrf = getCsrf();
                fetch('{{ route('tickets.report_journal') }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrf,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({})
                })
                .then(r => r.json())
                .then(function(data){
                    const msg = (data && data.message) ? data.message : (data && data.success ? 'گزارش شما ثبت شد. تیم پشتیبانی بررسی می‌کند.' : 'امکان ثبت گزارش وجود ندارد.');
                    try { modernAlert(msg, data && data.success ? 'success' : 'error'); } catch(_) { alert(msg); }
                    if (data && data.success) {
                        reportLink.style.pointerEvents = 'none';
                        reportLink.style.opacity = '0.6';
                        reportLink.textContent = 'گزارش ثبت شد';
                    }
                })
                .catch(function(){
                    try { modernAlert('خطا در ارتباط با سرور', 'error'); } catch(_) { alert('خطا در ارتباط با سرور'); }
                });
            });
        })();

        // Select period helper
        window.selectPeriod = function(id) {
            const form = document.querySelector('.filter-bar');
            if (!form) return;
            const selectEl = form.querySelector('select[name="period_id"]');
            if (selectEl) {
                selectEl.value = String(id);
                form.submit();
            }
        };

        // Modern start period modal controls
        window.openStartPeriodModal = function() {
            const modal = document.getElementById('startPeriodModal');
            if (!modal) return;
            // Attach overlay to body to ensure full-viewport coverage regardless of page layout
            if (modal.parentElement !== document.body) {
                document.body.appendChild(modal);
            }
            // Lock background scroll and display centered modal
            document.body.classList.add('modal-open');
            modal.style.display = 'flex';
            // Keep viewport focused at top to reveal modal immediately on tall pages
            try { window.scrollTo({ top: 0, behavior: 'smooth' }); } catch(e) {}
            // Trigger fade-in and scale animation
            setTimeout(() => modal.classList.add('show'), 10);
        };
        window.closeStartPeriodModal = function() {
            const modal = document.getElementById('startPeriodModal');
            modal.classList.remove('show');
            setTimeout(() => { modal.style.display = 'none'; }, 300);
            document.body.classList.remove('modal-open');
        };
        // Close on overlay click
        const overlay = document.getElementById('startPeriodModal');
        overlay.addEventListener('click', function(e){ if(e.target === overlay){ closeStartPeriodModal(); } });
        // Close on escape
        document.addEventListener('keydown', function(e){
            if(e.key === 'Escape'){
                const m = document.getElementById('startPeriodModal');
                if(m && m.style.display !== 'none'){ closeStartPeriodModal(); }
            }
        });
        // Validate start period form (prevent empty or whitespace-only names)
        window.validateStartPeriodForm = function(form){
            const input = form.querySelector('#periodName');
            if (!input) return true;
            const value = (input.value || '').trim();
            if (!value) {
                input.classList.add('input-error');
                try { modernAlert('نام دوره نباید خالی باشد', 'error'); } catch(e) {}
                input.focus();
                return false;
            }
            input.classList.remove('input-error');
            input.value = value; // submit trimmed value
            return true;
        };
        // Live button enable/disable based on trimmed input
        function updateStartBtn(){
            const form = document.getElementById('startPeriodForm');
            if (!form) return;
            const input = form.querySelector('#periodName');
            const btn = form.querySelector('button[type="submit"]');
            if (!input || !btn) return;
            const ok = (input.value || '').trim().length > 0;
            btn.disabled = !ok;
            input.classList.toggle('input-error', !ok);
        }
        document.addEventListener('input', function(e){
            if (e.target && e.target.id === 'periodName') { updateStartBtn(); }
        });
        // Initialize state when modal opens
        const modal = document.getElementById('startPeriodModal');
        modal.addEventListener('transitionend', function(){ updateStartBtn(); });
        // Also initialize after DOM ready
        updateStartBtn();
        // PnL Per Trade Chart
        var pnlOptions = {
            chart: {
                type: 'bar',
                height: 350,
                toolbar: {
                    show: true,
                    tools: {
                        pan: true,
                        zoom: true
                    }
                }
            },
            series: [{
                name: 'PnL',
                data: {!! json_encode($pnlChartData) !!}
            }],
            title: {
                text: 'سود/زیان بر حسب معامعه',
                align: 'left',
                style: {
                    color: '#fff'
                }
            },
            xaxis: {
                type: 'category',
                labels: {
                    show: false // Hide x-axis labels to avoid clutter
                }
            },
            yaxis: {
                labels: {
                    style: { colors: '#adb5bd' },
                    formatter: (value) => { return '$' + value.toFixed(2); }
                }
            },
            colors: [function({ value }) {
                return value >= 0 ? '#28a745' : '#dc3545'
            }],
            plotOptions: {
                bar: {
                    horizontal: false,
                    columnWidth: '60%',
                }
            },
            dataLabels: { enabled: false },
            grid: {
                borderColor: 'rgba(255, 255, 255, 0.1)'
            },
            tooltip: {
                theme: 'dark',
                x: {
                    formatter: function(val, { series, seriesIndex, dataPointIndex, w }) {
                        return w.globals.initialSeries[seriesIndex].data[dataPointIndex].date;
                    }
                },
                y: {
                    formatter: function (val) {
                        return "$" + val.toFixed(2)
                    }
                }
            }
        };

        var pnlChart = new ApexCharts(document.querySelector("#pnlChart"), pnlOptions);
        pnlChart.render();

        // Prepare cumulative series data as datetime
        var cumulativePnlRaw = {!! json_encode($cumulativePnl) !!};
        // Deduplicate by date: keep only the last trade per day
        var cumulativePnlData = (function(){
            var items = (cumulativePnlRaw || []).filter(function(i){ return i && i.date; });
            var lastByDay = {};
            for (var k = 0; k < items.length; k++) {
                var it = items[k];
                // Use the provided day string (YYYY-MM-DD) as key; later items override earlier ones
                lastByDay[it.date] = it;
            }
            var days = Object.keys(lastByDay).sort();
            return days.map(function(d){
                var it = lastByDay[d];
                return { x: new Date(d).getTime(), y: it.y };
            });
        })();

        // Cumulative PnL Chart
        var cumulativePnlOptions = {
            chart: {
                type: 'area',
                height: 350,
                toolbar: {
                    show: true,
                    tools: {
                        pan: true,
                        zoom: true
                    }
                },
                zoom: {
                    type: 'x',
                    enabled: true,
                    autoScaleYaxis: true
                },
            },
            series: [{
                name: 'Cumulative PnL',
                data: cumulativePnlData
            }],
            title: {
                text: 'سود/زیان تجمعی معاملات',
                align: 'left',
                style: {
                    color: '#fff'
                }
            },
            xaxis: {
                type: 'datetime',
                labels: { style: { colors: '#adb5bd' } }
            },
            yaxis: {
                labels: {
                    style: { colors: '#adb5bd' },
                    formatter: (value) => { return '$' + value.toFixed(2); }
                }
            },
            stroke: {
                curve: 'smooth',
                width: 2
            },
            fill: {
                type: 'gradient',
                gradient: {
                    shadeIntensity: 1,
                    opacityFrom: 0.7,
                    opacityTo: 0.3,
                    stops: [0, 90, 100]
                }
            },
            dataLabels: { enabled: false },
            grid: {
                borderColor: 'rgba(255, 255, 255, 0.1)'
            },
            tooltip: {
                theme: 'dark',
                x: { format: 'dd MMM yyyy HH:mm' },
                 y: {
                    formatter: function (val) {
                        return "$" + val.toFixed(2)
                    }
                }
            }
        };

        var cumulativePnlChart = new ApexCharts(document.querySelector("#cumulativePnlChart"), cumulativePnlOptions);
        cumulativePnlChart.render();

        // Prepare cumulative percent series as datetime (client-side compounding from per-trade percent)
        var perTradePercentRaw = {!! json_encode($perTradePercentSeries) !!};
        var cumulativePnlPercentData = (function(){
            var items = (perTradePercentRaw || []).filter(function(i){ return i && i.date; });
            var byDay = {};
            for (var k = 0; k < items.length; k++) {
                var it = items[k];
                var d = it.date;
                if (!byDay[d]) byDay[d] = [];
                byDay[d].push(it);
            }
            var days = Object.keys(byDay).sort();
            var compound = 1.0;
            var out = [];
            for (var i = 0; i < days.length; i++) {
                var day = days[i];
                var dayItems = byDay[day];
                var dayFactor = 1.0;
                for (var j = 0; j < dayItems.length; j++) {
                    var perTradePercent = parseFloat(dayItems[j].y || 0);
                    dayFactor = dayFactor * (1.0 + (perTradePercent / 100.0));
                }
                compound = compound * dayFactor;
                out.push({ x: new Date(day).getTime(), y: (compound - 1.0) * 100.0 });
            }
            return out;
        })();

        // Cumulative PnL Percent Chart
        var cumulativePnlPercentOptions = {
            chart: {
                type: 'area',
                height: 350,
                toolbar: { show: true },
                zoom: { enabled: true }
            },
            series: [{
                name: 'Cumulative PnL %',
                data: cumulativePnlPercentData
            }],
            title: {
                text: 'درصد سود/زیان تجمعی',
                align: 'left',
                style: { color: '#fff' }
            },
            xaxis: {
                type: 'datetime',
                labels: { style: { colors: '#adb5bd' } }
            },
            yaxis: {
                labels: {
                    style: { colors: '#adb5bd' },
                    formatter: (value) => { return value.toFixed(2) + '%'; }
                }
            },
            stroke: { curve: 'smooth', width: 2 },
            fill: {
                type: 'gradient',
                gradient: {
                    shadeIntensity: 1,
                    opacityFrom: 0.7,
                    opacityTo: 0.3,
                    stops: [0, 90, 100]
                }
            },
            dataLabels: { enabled: false },
            grid: { borderColor: 'rgba(255, 255, 255, 0.1)' },
            tooltip: {
                theme: 'dark',
                x: { format: 'dd MMM yyyy HH:mm' },
                y: {
                    formatter: function (val) {
                        return val.toFixed(2) + "%"
                    }
                }
            }
        };

        var cumulativePnlPercentChart = new ApexCharts(document.querySelector("#cumulativePnlPercentChart"), cumulativePnlPercentOptions);
        cumulativePnlPercentChart.render();
    });
</script>
@endpush
