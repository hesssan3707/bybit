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
        display: flex;
        align-items: center;
        gap: 8px;
        height: 60px;
    }
    .main-header .logo img {
        height: 36px; /* fits nicely in 60px header */
        width: auto;
        display: block;
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
        cursor: pointer;
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
    .mobile-footer-nav a, .mobile-footer-nav .dropup-toggle {
        display: flex;
        flex-direction: column;
        align-items: center;
        text-decoration: none;
        color: #555;
        font-size: 11px;
        flex-grow: 1;
        background: none;
        border: none;
        cursor: pointer;
    }
    .mobile-footer-nav a.selected {
        color: var(--primary-color); /* Highlight color for selected mobile icon */
    }
    .mobile-footer-nav .icon {
        font-size: 22px;
        margin-bottom: 2px;
    }
    .mobile-footer-nav .icon svg {
        width: 22px;
        height: 22px;
        display: block;
        stroke: currentColor;
        fill: none;
        stroke-width: 1.8;
        stroke-linecap: round;
        stroke-linejoin: round;
    }

    .dropup-menu {
        position: fixed;
        bottom: 60px;
        left: 0;
        right: 0;
        background-color: #fff;
        box-shadow: 0 -2px 5px rgba(0,0,0,0.1);
        z-index: 999;
        flex-direction: column;
    }
    .dropup-menu a {
        padding: 15px;
        text-align: center;
        border-bottom: 1px solid #eee;
        text-decoration: none;
        color: #333;
        font-weight: 500;
        transition: background-color 0.3s;
    }
    .dropup-menu a:hover {
        background-color: #f5f5f5;
    }
    .dropup-menu a:last-child {
        border-bottom: none;
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
    <div class="logo">
        <img src="{{ asset('public/logos/bridge-logo.png') }}" alt="Bridge" decoding="async">
    </div>
    <nav class="nav-links">
        <!-- Balance Menu -->
        <a href="{{ route('balance') }}" style="margin: 0 15px;">موجودی‌ها</a>

        <!-- Futures Trading Menu -->
        <div style="display: inline-block; position: relative; margin: 0 15px;">
            <a href="#" onclick="toggleFuturesMenu(event)">معاملات آتی ▼</a>
            <div id="futuresMenu" class="dropdown-list">
                <a href="{{ route('futures.orders') }}">تاریخچه سفارش‌ها</a>
                <a href="{{ route('futures.pnl_history') }}">سود و زیان</a>
                <a href="{{ route('futures.journal') }}">ژورنال</a>
                @if(!auth()->user()?->isWatcher())
                    <a href="{{ route('futures.order.create') }}">سفارش آتی جدید</a>
                @endif
            </div>
        </div>

        <!-- Strategies Menu -->
        <div style="display: inline-block; position: relative; margin: 0 15px;">
            <a href="#" onclick="toggleStrategiesMenu(event)">استراتژی‌ها ▼</a>
            <div id="strategiesMenu" class="dropdown-list">
                <a href="{{ route('strategies.macd') }}">MACD Strategy</a>
                <a href="{{ route('strategies.funding') }}">تحلیل فاندینگ و اوپن اینترست</a>
            </div>
        </div>

        <!-- Spot Trading Menu -->
        <div style="display: inline-block; position: relative; margin: 0 15px;">
            <a href="#" onclick="toggleSpotMenu(event)">معاملات اسپات ▼</a>
            <div id="spotMenu" class="dropdown-list">
                <a href="{{ route('spot.orders.view') }}">سفارش‌های اسپات</a>
                @if(!auth()->user()?->isWatcher())
                    <a href="{{ route('spot.order.create.view') }}">سفارش اسپات جدید</a>
                @endif
            </div>
        </div>

        <!-- API Documentation Link -->
        <a href="{{ route('api.documentation') }}" style="margin: 0 15px; color: #667eea; font-weight: 600;" title="مستندات API">API مستندات</a>

        @if(auth()->user()?->isAdmin())
        <!-- Admin Menu (only for admin users) -->
        <div style="display: inline-block; position: relative; margin: 0 15px;">
            <a href="#" onclick="toggleAdminMenu(event)">مدیریت ▼</a>
            <div id="adminMenu" class="dropdown-list">
                <a href="{{ route('admin.all-users') }}">همه کاربران</a>
                <a href="{{ route('admin.pending-exchanges') }}">درخواست‌های صرافی</a>
                <a href="{{ route('admin.all-exchanges') }}">همه صرافی‌ها</a>
                <a href="{{ route('admin.company-requests.pending') }}">درخواست‌های صرافی شرکت</a>
                <a href="{{ route('admin.tickets') }}">تیکت‌ها</a>
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
<div id="dropup-menu" class="dropup-menu">
    <a href="{{ route('futures.orders') }}">سفارش‌ها</a>
    <a href="{{ route('futures.pnl_history') }}">سود و زیان</a>
    <a href="{{ route('futures.journal') }}">ژورنال</a>
    <a href="{{ route('strategies.macd') }}">استراتژی MACD</a>
    <a href="{{ route('strategies.funding') }}">تحلیل فاندینگ و اوپن اینترست</a>
    <a href="{{ route('api.documentation') }}">مستندات</a>
</div>
<nav class="mobile-footer-nav">
    @if(!auth()->user()?->isWatcher())
        <a href="{{ route('futures.order.create') }}">
            <span class="icon">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <circle cx="12" cy="12" r="9"></circle>
                    <path d="M12 8v8M8 12h8"></path>
                </svg>
            </span>
            <span>جدید</span>
        </a>
    @endif
    <a href="{{ route('spot.orders.view') }}">
        <span class="icon">
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <circle cx="10" cy="12" r="5"></circle>
                <circle cx="15" cy="10" r="5"></circle>
            </svg>
        </span>
        <span>اسپات</span>
    </a>
    <a href="{{ route('balance') }}">
        <span class="icon">
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <rect x="3" y="6" width="18" height="12" rx="3"></rect>
                <path d="M3 10h18"></path>
                <path d="M7 14h5"></path>
            </svg>
        </span>
        <span>موجودی</span>
    </a>
    <a href="{{ route('profile.index') }}">
        <span class="icon">
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <circle cx="12" cy="8" r="4"></circle>
                <path d="M6 20c0-3 3-5 6-5s6 2 6 5"></path>
            </svg>
        </span>
        <span>پروفایل</span>
    </a>
    <button class="dropup-toggle" onclick="toggleDropUpMenu()">
        <span class="icon">
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M4 7h16"></path>
                <path d="M4 12h16"></path>
                <path d="M4 17h16"></path>
            </svg>
        </span>
        <span>بیشتر</span>
    </button>
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
        const dropdownLinks = document.querySelectorAll('#futuresMenu a, #spotMenu a, #adminMenu a, #strategiesMenu a');
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
        const dropdowns = document.getElementsByClassName('dropdown-list');

        for (let i = 0; i < dropdowns.length; i++) {
            dropdowns[i].style.display = 'none';
        }
        const mobile_dropups = document.getElementsByClassName('dropup-menu');

        for (let i = 0; i < mobile_dropups.length; i++) {
            mobile_dropups[i].style.display = 'none';
        }


        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const spotMenu = document.getElementById('spotMenu');
            const adminMenu = document.getElementById('adminMenu');
            const futuresMenu = document.getElementById('futuresMenu');
            const strategiesMenu = document.getElementById('strategiesMenu');
            const spotMenuToggle = event.target.closest('[onclick*="toggleSpotMenu"]');
            const adminMenuToggle = event.target.closest('[onclick*="toggleAdminMenu"]');
            const futuresMenuToggle = event.target.closest('[onclick*="toggleFuturesMenu"]');
            const strategiesMenuToggle = event.target.closest('[onclick*="toggleStrategiesMenu"]');

            if (!spotMenuToggle && spotMenu) {
                spotMenu.style.display = 'none';
            }

            if (!adminMenuToggle && adminMenu) {
                adminMenu.style.display = 'none';
            }

            if (!futuresMenuToggle && futuresMenu) {
                futuresMenu.style.display = 'none';
            }

            if (!strategiesMenuToggle && strategiesMenu) {
                strategiesMenu.style.display = 'none';
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

    function toggleStrategiesMenu(event) {
        event.preventDefault();
        event.stopPropagation();
        const menu = document.getElementById('strategiesMenu');
        menu.style.display = menu.style.display === 'none' ? 'block' : 'none';
    }

    function toggleDropUpMenu() {
        const menu = document.getElementById('dropup-menu');
        menu.style.display = menu.style.display === 'none' ? 'flex' : 'none';
    }
</script>
