<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#345cf5">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <title>@yield('title', 'Bayiinet') - XML Bayilik Sistemi</title>
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='7' fill='%23345cf5'/%3E%3Ctext x='16' y='22' text-anchor='middle' font-family='sans-serif' font-weight='700' font-size='17' fill='white'%3EB%3C/text%3E%3C/svg%3E">
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root {
            --sidebar-w: 240px;
            --ink: #172033;
            --brand: #345cf5;
            --soft: #f1f5f9;
            --topbar-h: 56px;
        }
        * { box-sizing: border-box; }
        html { -webkit-text-size-adjust: 100%; }
        body {
            background: #f5f7fb;
            color: var(--ink);
            font-size: .92rem;
            margin: 0;
            padding: 0;
            overflow-x: hidden;
            min-height: 100dvh;
        }
        img { max-width: 100%; height: auto; }
        a, button, .btn { -webkit-tap-highlight-color: transparent; }
        .btn { min-height: 38px; }
        .btn-sm { min-height: 32px; }
        .form-control, .form-select { min-height: 42px; font-size: 16px; } /* iOS zoom önleme */

        /* —— Auth panel (admin / üye) —— */
        .sidebar {
            width: var(--sidebar-w);
            background: #fff;
            color: #354052;
            position: fixed;
            top: var(--topbar-h);
            left: 0;
            bottom: 0;
            overflow-y: auto;
            overflow-x: hidden;
            border-right: 1px solid #e8ebf2;
            z-index: 1010;
            -webkit-overflow-scrolling: touch;
        }
        .sidebar a {
            color: #4b5565;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: .5rem;
            padding: .65rem .9rem;
            border-radius: .5rem;
            margin: .1rem .55rem;
            font-size: .88rem;
        }
        .sidebar a:hover, .sidebar a.active { background: #edf1ff; color: #294bd4; }
        .sidebar .nav-section {
            padding: .75rem 1rem .25rem;
            font-size: .65rem;
            letter-spacing: .08em;
            color: #8b93a3;
            text-transform: uppercase;
        }
        .topbar {
            position: fixed;
            top: 0; left: 0; right: 0;
            height: var(--topbar-h);
            z-index: 1020;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: .75rem;
            padding: 0 .9rem;
            background: #fff;
            border-bottom: 1px solid #e8ebf2;
        }
        .topbar-brand { font-weight: 800; color: #172033; text-decoration: none; font-size: 1.05rem; white-space: nowrap; }
        .topbar-brand span { color: var(--brand); }
        .main {
            margin-left: var(--sidebar-w);
            padding: 1rem;
            padding-top: calc(var(--topbar-h) + 1rem);
            min-height: 100dvh;
            width: auto;
            max-width: 100%;
        }
        .card { border: 1px solid #ebeff5; border-radius: .8rem; box-shadow: 0 2px 8px rgba(20,35,70,.035); }
        .stat-card { border-top: 3px solid #3b63f3; }
        .stat-card.green { border-top-color: #21a576; }
        .stat-card.orange { border-top-color: #f2a900; }
        .stat-card.red { border-top-color: #ef4444; }
        .hero-banner {
            border-radius: .85rem;
            color: #fff;
            background: linear-gradient(110deg, #315cef, #8542e9);
            padding: 1.1rem 1.2rem;
        }
        .table-responsive { -webkit-overflow-scrolling: touch; }
        .table { font-size: .85rem; }
        .menu-toggle {
            display: none;
            border: 1px solid #e2e8f0;
            background: #fff;
            border-radius: .5rem;
            width: 42px; height: 42px;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            color: var(--ink);
            flex-shrink: 0;
        }
        .sidebar-backdrop {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, .45);
            z-index: 1005;
        }
        .sidebar-backdrop.show { display: block; }

        @media (max-width: 991.98px) {
            :root { --sidebar-w: 0px; }
            .menu-toggle { display: inline-flex; }
            .sidebar {
                width: min(280px, 86vw);
                transform: translateX(-105%);
                transition: transform .25s ease;
                top: 0;
                z-index: 1040;
                box-shadow: 8px 0 24px rgba(0,0,0,.12);
            }
            .sidebar.open { transform: translateX(0); }
            .main {
                margin-left: 0;
                padding: .85rem;
                padding-top: calc(var(--topbar-h) + .85rem);
            }
            .topbar { padding: 0 .65rem; }
            h4, .h4 { font-size: 1.15rem; }
            .row.g-3 > [class*="col-"] { margin-bottom: 0; }
        }

        @media (max-width: 575.98px) {
            body { font-size: .9rem; }
            .main { padding: .65rem; padding-top: calc(var(--topbar-h) + .65rem); }
            .card-body { padding: .9rem; }
            .btn-lg { width: 100%; }
            .d-flex.justify-content-between.align-items-center.mb-4 {
                flex-direction: column;
                align-items: stretch !important;
                gap: .65rem;
            }
        }

        /* guest / storefront */
        .storefront-pad { padding-bottom: env(safe-area-inset-bottom); }
        @stack('styles')
    </style>
    @include('partials.product-card-styles')
    @stack('head')
</head>
<body>
@auth
    <div class="sidebar-backdrop" id="sidebarBackdrop" onclick="closeSidebar()"></div>

    <div class="topbar">
        <div class="d-flex align-items-center gap-2 min-w-0">
            <button type="button" class="menu-toggle" id="menuToggle" aria-label="Menü" onclick="toggleSidebar()">
                <i class="bi bi-list"></i>
            </button>
            <a href="{{ auth()->user()->isAdmin() ? route('admin.dashboard') : route('home') }}" class="topbar-brand">
                Bayi<span>inet</span>
            </a>
        </div>
        <div class="d-flex align-items-center gap-2 flex-shrink-0">
            @if(auth()->user()->isDealer() && auth()->user()->dealer)
                <span class="badge text-bg-light border d-none d-sm-inline-block">
                    {{ number_format(auth()->user()->dealer->balance, 2, ',', '.') }} ₺
                </span>
            @endif
            <span class="small text-muted d-none d-md-inline text-truncate" style="max-width:140px">{{ auth()->user()->name }}</span>
            @if(auth()->user()->isAdmin())
                <a href="{{ route('home') }}" class="btn btn-sm btn-outline-secondary d-none d-sm-inline-flex">Site</a>
            @endif
            <form action="{{ route('logout') }}" method="POST" class="m-0">
                @csrf
                <button type="submit" class="btn btn-sm btn-outline-secondary">Çıkış</button>
            </form>
        </div>
    </div>

    <div class="sidebar" id="appSidebar">
        <nav class="py-2">
            @if(auth()->user()->isAdmin())
                <div class="nav-section">Genel</div>
                <a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
                <a href="{{ route('admin.orders.index') }}" class="{{ request()->routeIs('admin.orders.*') ? 'active' : '' }}">
                    <i class="bi bi-bag-check"></i> Siparişler
                </a>
                <a href="{{ route('admin.customers.index') }}" class="{{ request()->routeIs('admin.customers.*') ? 'active' : '' }}">
                    <i class="bi bi-person-lines-fill"></i> Müşteriler
                </a>
                <div class="nav-section">Katalog</div>
                <a href="{{ route('admin.sources.index') }}" class="{{ request()->routeIs('admin.sources.*') ? 'active' : '' }}">
                    <i class="bi bi-hdd-network"></i> XML Kaynakları
                </a>
                <a href="{{ route('admin.products.index') }}" class="{{ request()->routeIs('admin.products.*') && !request()->routeIs('admin.products.critical') ? 'active' : '' }}">
                    <i class="bi bi-box-seam"></i> Ürünler
                </a>
                <a href="{{ route('admin.products.critical') }}" class="{{ request()->routeIs('admin.products.critical') ? 'active' : '' }}">
                    <i class="bi bi-exclamation-triangle"></i> Kritik Stok
                </a>
                <a href="{{ route('admin.dealers.index') }}" class="{{ request()->routeIs('admin.dealers.*') ? 'active' : '' }}">
                    <i class="bi bi-people"></i> Bayiler
                </a>
                <a href="{{ route('admin.blacklist.index') }}" class="{{ request()->routeIs('admin.blacklist.*') ? 'active' : '' }}">
                    <i class="bi bi-slash-circle"></i> Kara Liste
                </a>
                <div class="nav-section">Pazaryeri</div>
                <a href="{{ route('admin.marketplace.index') }}" class="{{ request()->routeIs('admin.marketplace.*') ? 'active' : '' }}">
                    <i class="bi bi-shop"></i> Pazaryeri Yönetimi
                </a>
                <a href="{{ route('admin.announcements.index') }}" class="{{ request()->routeIs('admin.announcements.*') ? 'active' : '' }}">
                    <i class="bi bi-megaphone"></i> İlanlar
                </a>
                <a href="{{ route('admin.taxonomy.index') }}" class="{{ request()->routeIs('admin.taxonomy.*') ? 'active' : '' }}">
                    <i class="bi bi-tags"></i> Kategori & Marka
                </a>
                <a href="{{ route('admin.pricing.index') }}" class="{{ request()->routeIs('admin.pricing.*') ? 'active' : '' }}">
                    <i class="bi bi-percent"></i> Kâr & Fiyatlama
                </a>
                <div class="nav-section">Sistem</div>
                <a href="{{ route('admin.automation.index') }}" class="{{ request()->routeIs('admin.automation.*') ? 'active' : '' }}">
                    <i class="bi bi-robot"></i> Otomasyon
                </a>
                <a href="{{ route('admin.logs.index') }}" class="{{ request()->routeIs('admin.logs.*') ? 'active' : '' }}">
                    <i class="bi bi-list-ul"></i> Kayıtlar
                </a>
                <a href="{{ route('admin.settings.index') }}" class="{{ request()->routeIs('admin.settings.*') ? 'active' : '' }}">
                    <i class="bi bi-sliders"></i> Ayarlar
                </a>
            @else
                @if(auth()->user()->dealer?->isActive())
                    <a href="{{ route('home') }}" class="{{ request()->routeIs('home') ? 'active' : '' }}">
                        <i class="bi bi-house"></i> Katalog
                    </a>
                    <a href="{{ route('dealer.orders.index') }}" class="{{ request()->routeIs('dealer.orders.*') ? 'active' : '' }}">
                        <i class="bi bi-cart-check"></i> Siparişlerim
                    </a>
                    <a href="{{ route('dealer.account') }}" class="{{ request()->routeIs('dealer.account*') ? 'active' : '' }}">
                        <i class="bi bi-person"></i> Hesabım
                    </a>
                @else
                    <a href="{{ route('dealer.application') }}" class="active">
                        <i class="bi bi-hourglass-split"></i> Başvuru Durumu
                    </a>
                @endif
            @endif
        </nav>
    </div>

    <div class="main">
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger alert-dismissible fade show">
                <ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif
        @yield('content')
    </div>

    <script>
        function toggleSidebar() {
            const s = document.getElementById('appSidebar');
            const b = document.getElementById('sidebarBackdrop');
            s.classList.toggle('open');
            b.classList.toggle('show');
            document.body.style.overflow = s.classList.contains('open') ? 'hidden' : '';
        }
        function closeSidebar() {
            document.getElementById('appSidebar')?.classList.remove('open');
            document.getElementById('sidebarBackdrop')?.classList.remove('show');
            document.body.style.overflow = '';
        }
        document.querySelectorAll('#appSidebar a').forEach(a => a.addEventListener('click', closeSidebar));
    </script>
@else
    {{-- Misafir / giriş sayfaları: basit mobil üst bar --}}
    <nav class="navbar navbar-expand-lg bg-white border-bottom sticky-top">
        <div class="container">
            <a class="navbar-brand fw-bold" href="{{ route('home') }}" style="color:#172033">Bayi<span style="color:#345cf5">inet</span></a>
            <div class="d-flex gap-2">
                <a href="{{ route('login') }}" class="btn btn-sm btn-outline-secondary">Giriş</a>
                <a href="{{ route('register') }}" class="btn btn-sm btn-primary">Başvuru</a>
            </div>
        </div>
    </nav>
    <main class="storefront-pad">
        @if(session('success'))
            <div class="container pt-3"><div class="alert alert-success">{{ session('success') }}</div></div>
        @endif
        @if(session('error'))
            <div class="container pt-3"><div class="alert alert-danger">{{ session('error') }}</div></div>
        @endif
        @yield('content')
    </main>
    <footer class="border-top bg-white mt-auto py-3">
        <div class="container small text-muted d-flex flex-wrap gap-3 justify-content-between">
            <span>&copy; {{ date('Y') }} Bayiinet</span>
            <span class="d-flex flex-wrap gap-3">
                <a href="{{ route('pages.gizlilik') }}" class="text-muted text-decoration-none">Gizlilik</a>
                <a href="{{ route('pages.mesafeli') }}" class="text-muted text-decoration-none">Mesafeli Satış</a>
                <a href="{{ route('pages.iade') }}" class="text-muted text-decoration-none">İade</a>
                <a href="{{ route('pages.contact') }}" class="text-muted text-decoration-none">İletişim</a>
            </span>
        </div>
    </footer>
@endauth
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
@stack('scripts')
</body>
</html>
