<style>
    /* Web Header */
    .main-header {
        background-color: #fff;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        padding: 0 20px;
        position: fixed;
        top: 0;
        right: 0;
        left: 0;
        z-index: 1000;
        display: flex;
        justify-content: space-between;
        align-items: center;
        height: 60px;
    }
    .main-header .logo {
        font-weight: bold;
        font-size: 1.5em;
    }
    .main-header .nav-links {
        display: flex;
        align-items: center;
        flex-grow: 1;
        justify-content: center;
    }
    .main-header .nav-links a, .main-header .nav-links form button {
        text-decoration: none;
        color: #555;
        margin: 0 15px;
        padding: 5px 10px;
        border-radius: 5px;
        transition: background-color 0.3s, color 0.3s;
        font-weight: 500;
    }
    .main-header .nav-links a:hover, .main-header .nav-links form button:hover {
        background-color: var(--primary-color);
        color: #fff;
    }
    .main-header .nav-links form {
        display: inline-block;
        margin: 0;
        padding: 0;
    }
    .main-header .nav-links form button {
        border: none;
        background: none;
        cursor: pointer;
        font-size: inherit;
        font-family: inherit;
        padding: 5px 10px; /* Match link padding */
        margin: 0;
    }
    .main-header .equity {
        font-weight: bold;
        color: var(--primary-color);
    }

    /* Mobile Sticky Footer */
    .mobile-footer-nav {
        display: none; /* Hidden by default */
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        height: 60px;
        background-color: #fff;
        box-shadow: 0 -2px 5px rgba(0,0,0,0.1);
        z-index: 1000;
        justify-content: space-around;
        align-items: center;
    }
    .mobile-footer-nav a, .mobile-footer-nav form button {
        display: flex;
        flex-direction: column;
        align-items: center;
        text-decoration: none;
        color: #555;
        font-size: 12px;
        flex-grow: 1;
    }
     .mobile-footer-nav form button {
        border: none;
        background: none;
        cursor: pointer;
        font-size: inherit;
        font-family: inherit;
        padding: 0;
        margin: 0;
    }
    .mobile-footer-nav .icon {
        font-size: 24px;
        margin-bottom: 2px;
    }

    /* Responsive Toggle */
    @media screen and (max-width: 768px) {
        .main-header {
            display: none;
        }
        .mobile-footer-nav {
            display: flex;
        }
    }
</style>

<!-- Web Header -->
<header class="main-header">
    <div class="logo">Trading Helper</div>
    <nav class="nav-links">
        <a href="{{ route('orders.index') }}">سفارش‌ها</a>
        <a href="{{ route('pnl.history') }}">تاریخچه سود و زیان</a>
        <a href="{{ route('order.create') }}">سفارش جدید</a>
        <div class="equity">Equity: ${{-- $totalEquity ?? 'N/A' --}}</div>
        <form action="{{ route('logout') }}" method="POST">
            @csrf
            <button type="submit">خروج</button>
        </form>
    </nav>
</header>

<!-- Mobile Sticky Footer -->
<nav class="mobile-footer-nav">
    <a href="{{ route('orders.index') }}">
        <span class="icon">📊</span>
        <span>سفارش‌ها</span>
    </a>
    <a href="{{ route('pnl.history') }}">
        <span class="icon">📜</span>
        <span>سود و زیان</span>
    </a>
    <a href="{{ route('order.create') }}">
        <span class="icon">➕</span>
        <span>جدید</span>
    </a>
    <a href="#">
        <span class="icon">💰</span>
        <span>${{-- $totalEquity ?? 'N/A' --}}</span>
    </a>
    <form action="{{ route('logout') }}" method="POST">
        @csrf
        <button type="submit">
            <span class="icon">🚪</span>
            <span>خروج</span>
        </button>
    </form>
</nav>
