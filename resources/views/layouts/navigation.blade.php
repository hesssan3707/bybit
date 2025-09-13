<style>
    /* Web Header */
    .main-header {
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
        backdrop-filter: blur(5px);
        -webkit-backdrop-filter: blur(5px);
    }
    .main-header .logo {
        font-weight: bold;
        font-size: 1.5em;
        text-shadow: white 0px 0px 10px;
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
        color: #eaeaea;
        margin: 0 15px;
        padding: 5px 10px;
        border-radius: 5px;
        transition: background-color 0.3s, color 0.3s;
        font-weight: 500;
        text-shadow:
        0.07em 0 black,
        0 0.07em black,
        -0.07em 0 black,
        0 -0.07em black;
    }
    .main-header a:hover {
        text-decoration: none;
        color: #000000;
        background-color : #00f2fe;
        margin: 0 15px;
        padding: 5px 10px;
        border-radius: 5px;
        transition: background-color 0.3s, color 0.3s;
        font-weight: 500;
    }
    .main-header .dropdown-list {
        display: none;
        position: absolute;
        top: 100%; left: 0;
        background: rgba(150, 150, 150, 0.6);
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        border-radius: 5px;
        min-width: 200px;
        z-index: 1001;
        margin-top:20px;
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
    }
    .main-header .dropdown-list a{
        display: block;
        padding: 10px 15px;
        margin: 0;
    }
    /* Only apply selected/hover styling to header-right links and dropdown menu items */
    .main-header .header-right a:hover,
    .main-header .header-right a.selected,
    #futuresMenu a:hover,
    #spotMenu a:hover,
    #adminMenu a:hover {
        color: #020202;
    }
    /* Dropdown toggle links have different hover effect */
    .nav-links > div > a:hover {
        color: #333;
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
        font-size: 11px;
        flex-grow: 1;
    }
    .mobile-footer-nav a.selected {
        color: var(--primary-color); /* Highlight color for selected mobile icon */
    }
    .mobile-footer-nav .icon {
        font-size: 22px;
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
    <div class="logo">Trader Bridge</div>
    <nav class="nav-links">
        <!-- Balance Menu -->
        <a href="{{ route('balance') }}" style="margin: 0 15px;">موجودی‌ها</a>

        <!-- Futures Trading Menu -->
        <div style="display: inline-block; position: relative; margin: 0 15px;">
            <a href="#" style="cursor: pointer;" onclick="toggleFuturesMenu(event)">معاملات آتی ▼</a>
            <div id="futuresMenu" class="dropdown-list">
                <a href="{{ route('orders.index') }}">تاریخچه سفارش‌ها</a>
                <a href="{{ route('pnl.history') }}">سود و زیان</a>
                <a href="{{ route('order.create') }}">سفارش آتی جدید</a>
            </div>
        </div>

        <!-- Spot Trading Menu -->
        <div style="display: inline-block; position: relative; margin: 0 15px;">
            <a href="#" style="cursor: pointer;" onclick="toggleSpotMenu(event)">معاملات اسپات ▼</a>
            <div id="spotMenu" class="dropdown-list">
                <a href="{{ route('spot.orders.view') }}">سفارش‌های اسپات</a>
                <a href="{{ route('spot.order.create.view') }}">سفارش اسپات جدید</a>
            </div>
        </div>

        <!-- API Documentation Link -->
        <a href="{{ route('api.documentation') }}" style="margin: 0 15px; color: #667eea; font-weight: 600;" title="مستندات API">API مستندات</a>

        @if(auth()->user()?->isAdmin())
        <!-- Admin Menu (only for admin users) -->
        <div style="display: inline-block; position: relative; margin: 0 15px;">
            <a href="#" style="cursor: pointer;" onclick="toggleAdminMenu(event)">مدیریت ▼</a>
            <div id="adminMenu" class="dropdown-list">
                <a href="{{ route('admin.all-users') }}">همه کاربران</a>
                <a href="{{ route('admin.pending-exchanges') }}">درخواست‌های صرافی</a>
                <a href="{{ route('admin.all-exchanges') }}">همه صرافی‌ها</a>
            </div>
        </div>
        @endif
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
    <a href="{{ route('order.create') }}">
        <span class="icon">➕</span>
        <span>جدید</span>
    </a>
    <a href="{{ route('spot.orders.view') }}">
        <span class="icon">💰</span>
        <span>اسپات</span>
    </a>
    <a href="{{ route('balance') }}">
        <span class="icon">💳</span>
        <span>موجودی</span>
    </a>
    <a href="{{ route('profile.index') }}">
        <span class="icon">👤</span>
        <span>پروفایل</span>
    </a>
</nav>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const currentPath = window.location.pathname;

        // Clear any existing selected styles first
        const allLinks = document.querySelectorAll('.main-header a');
        allLinks.forEach(link => {
            link.classList.remove('selected');
            link.style.backgroundColor = '';
            link.style.color = '';
        });

        // Only highlight dropdown menu items that match current path
        const dropdownLinks = document.querySelectorAll('#futuresMenu a, #spotMenu a, #adminMenu a');
        let hasActiveDropdownItem = false;
        dropdownLinks.forEach(link => {
            const href = link.getAttribute('href');
            if (href && href === currentPath) {
                link.style.backgroundColor = 'var(--primary-color)';
                link.style.color = '#fff';
                hasActiveDropdownItem = true;
            }
        });

        // Only highlight header-right links if no dropdown item is active
        if (!hasActiveDropdownItem) {
            const headerLinks = document.querySelectorAll('.main-header .header-right a[href]');
            headerLinks.forEach(link => {
                const href = link.getAttribute('href');
                if (href && href === currentPath) {
                    link.classList.add('selected');
                }
            });
        }

        // Mobile footer
        const mobileNavLinks = document.querySelectorAll('.mobile-footer-nav a');
        mobileNavLinks.forEach(link => {
            const href = link.getAttribute('href');
            if (href && href === currentPath) {
                link.classList.add('selected');
            }
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const spotMenu = document.getElementById('spotMenu');
            const adminMenu = document.getElementById('adminMenu');
            const futuresMenu = document.getElementById('futuresMenu');
            const spotMenuToggle = event.target.closest('[onclick*="toggleSpotMenu"]');
            const adminMenuToggle = event.target.closest('[onclick*="toggleAdminMenu"]');
            const futuresMenuToggle = event.target.closest('[onclick*="toggleFuturesMenu"]');

            if (!spotMenuToggle && spotMenu) {
                spotMenu.style.display = 'none';
            }

            if (!adminMenuToggle && adminMenu) {
                adminMenu.style.display = 'none';
            }

            if (!futuresMenuToggle && futuresMenu) {
                futuresMenu.style.display = 'none';
            }
        });
    });

    function toggleSpotMenu(event) {
        event.preventDefault();
        event.stopPropagation();
        const menu = document.getElementById('spotMenu');
        menu.style.display = menu.style.display === 'none' ? 'block' : 'none';
    }

    function toggleAdminMenu(event) {
        event.preventDefault();
        event.stopPropagation();
        const menu = document.getElementById('adminMenu');
        menu.style.display = menu.style.display === 'none' ? 'block' : 'none';
    }

    function toggleFuturesMenu(event) {
        event.preventDefault();
        event.stopPropagation();
        const menu = document.getElementById('futuresMenu');
        menu.style.display = menu.style.display === 'none' ? 'block' : 'none';
    }
</script>
