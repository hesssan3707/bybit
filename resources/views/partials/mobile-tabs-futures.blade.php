@push('styles')
<style>
    /* Mobile tabs wrapper */
    .mobile-tabs {
        display: none;
        margin-bottom: 12px;
    }
    @media (max-width: 768px) {
        .mobile-tabs { display: block; }
    }

    /* Reuse request-switch tab design from exchange creation */
    .mobile-tabs .request-switch {
        display: flex;
        width: 100%;
        gap: 0;
        border: 1px solid var(--border-color);
        border-radius: 12px;
        overflow: hidden;
        background: rgba(255,255,255,0.04);
        backdrop-filter: blur(6px);
    }
    .mobile-tabs .request-switch__btn {
        flex: 1;
        background: transparent;
        color: #fff;
        border: none;
        padding: 10px 12px;
        text-decoration: none;
        cursor: pointer;
        text-align: center;
        font-weight: 600;
        transition: background 0.2s ease, color 0.2s ease, box-shadow 0.2s ease;
        display: inline-block;
        font-size: 14px;
        line-height: 1.2;
        white-space: nowrap;
    }
    .mobile-tabs .request-switch__btn + .request-switch__btn { border-right: 1px solid var(--border-color); }
    .mobile-tabs .request-switch__btn:hover { background: rgba(255,255,255,0.08); }
    /* Subtle glassy active state */
    .mobile-tabs .request-switch__btn.active {
        background: rgba(255,255,255,0.14);
        color: #fff;
        font-weight: 700;
        box-shadow: inset 0 0 0 1px rgba(255,255,255,0.18), 0 6px 16px rgba(255,255,255,0.08);
        text-decoration: none;
    }
    .mobile-tabs .request-switch__btn:focus-visible {
        outline: none;
        box-shadow: 0 0 0 3px rgba(0,123,255,0.25);
    }
    /* Tighten if space is limited */
    @media (max-width: 400px) {
        .mobile-tabs .request-switch__btn { padding: 8px 10px; font-size: 13px; }
    }
    @media (max-width: 340px) {
        .mobile-tabs .request-switch__btn { padding: 8px 8px; font-size: 12px; }
    }
</style>
@endpush

<div class="mobile-tabs" dir="rtl">
    <nav class="request-switch" role="tablist" aria-label="فیوچرز - پیمایش صفحات">
        <a href="{{ route('futures.orders') }}"
           class="request-switch__btn {{ request()->routeIs('futures.orders') ? 'active' : '' }}"
           aria-current="{{ request()->routeIs('futures.orders') ? 'page' : 'false' }}">
             سفارش‌های آتی
        </a>
        <a href="{{ route('futures.pnl_history') }}"
           class="request-switch__btn {{ request()->routeIs('futures.pnl_history') ? 'active' : '' }}"
           aria-current="{{ request()->routeIs('futures.pnl_history') ? 'page' : 'false' }}">
             سود و زیان
        </a>
        <a href="{{ route('futures.journal') }}"
           class="request-switch__btn {{ request()->routeIs('futures.journal') ? 'active' : '' }}"
           aria-current="{{ request()->routeIs('futures.journal') ? 'page' : 'false' }}">
             ژورنال
        </a>
    </nav>
    <div style="height: 6px;"></div>
</div>