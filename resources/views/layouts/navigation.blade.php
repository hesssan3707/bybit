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
        margin: auto; /* This will center the nav */
    }
    .main-header .header-right {
        display: flex;
        align-items: center;
    }
    .main-header a {
        text-decoration: none;
        color: #555;
        margin: 0 15px;
        padding: 5px 10px;
        border-radius: 5px;
        transition: background-color 0.3s, color 0.3s;
        font-weight: 500;
    }
    .main-header a:hover, .main-header a.selected {
        background-color: var(--primary-color);
        color: #fff;
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
    .mobile-footer-nav a {
        display: flex;
        flex-direction: column;
        align-items: center;
        text-decoration: none;
        color: #555;
        font-size: 12px;
        flex-grow: 1;
    }
    .mobile-footer-nav a.selected {
        color: var(--primary-color); /* Highlight color for selected mobile icon */
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
    <div class="logo">Trader Assistant</div>
    <nav class="nav-links">
        <a href="{{ route('orders.index') }}">سفارش‌ها</a>
        <a href="{{ route('pnl.history') }}">تاریخچه سود و زیان</a>
        <a href="{{ route('order.create') }}">سفارش جدید</a>
    </nav>
    <div class="header-right">
        <a href="{{ route('profile.index') }}">پروفایل</a>
        <a href="#" onclick="event.preventDefault(); document.getElementById('logout-form').submit();">خروج</a>
        <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
            @csrf
        </form>
    </div>
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
    <a href="{{ route('profile.index') }}">
        <span class="icon">👤</span>
        <span>پروفایل</span>
    </a>
    <a href="#" onclick="event.preventDefault(); document.getElementById('mobile-logout-form').submit();">
    <span class="icon">🚪</span>
    <span>خروج</span>
    </a>
    <form id="mobile-logout-form" action="{{ route('logout') }}" method="POST" style="display:none;">
        @csrf
    </form>
</nav>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const currentPath = window.location.pathname;

        // Web header
        const webNavLinks = document.querySelectorAll('.main-header .nav-links a, .main-header .header-right a');
        webNavLinks.forEach(link => {
            if (link.getAttribute('href') === window.location.href) {
                link.classList.add('selected');
            }
        });

        // Mobile footer
        const mobileNavLinks = document.querySelectorAll('.mobile-footer-nav a');
        mobileNavLinks.forEach(link => {
            if (link.getAttribute('href') === window.location.href) {
                link.classList.add('selected');
            }
        });
    });
</script>
