<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Bayiinet') - XML Bayilik Sistemi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root { --sidebar-w: 226px; --ink: #172033; --brand: #345cf5; }
            body { background: #f5f7fb; color: var(--ink); font-size: .92rem; }
        .sidebar {
            width: var(--sidebar-w);
            min-height: 100vh;
                background: #fff;
                color: #354052;
            position: fixed;
                top: 64px; left: 0; bottom: 0; overflow-y: auto;
                border-right: 1px solid #e8ebf2;
                z-index: 1010;
            }
            .sidebar a { color: #4b5565; text-decoration: none; display: block; padding: .58rem .8rem; border-radius: .45rem; margin: .12rem .55rem; font-size: .83rem; }
            .sidebar a:hover, .sidebar a.active { background: #edf1ff; color: #294bd4; }
            .sidebar .brand { padding: .9rem 1rem; font-weight: 750; font-size: 1.02rem; color: #172033; border-bottom: 1px solid #edf0f5; }
            .sidebar .nav-section { padding: .7rem 1rem .2rem; font-size: .67rem; letter-spacing: .1em; color: #8b93a3; text-transform: uppercase; }
            .topbar { position: fixed; top: 0; left: 0; right: 0; height: 64px; z-index: 1020; display:flex; justify-content:space-between; align-items:center; padding: 0 1.2rem; background:#fff; border-bottom:1px solid #e8ebf2; }
            .topbar-brand { font-weight:800; color:#172033; }
            .main { margin-left: var(--sidebar-w); padding: 1.5rem; padding-top: 84px; }
            .card { border: 1px solid #ebeff5; border-radius: .8rem; box-shadow: 0 2px 8px rgba(20,35,70,.035); }
            .stat-card { border-top: 3px solid #3b63f3; }
            .stat-card.green { border-top-color: #21a576; }
            .stat-card.orange { border-top-color: #f2a900; }
            .stat-card.red { border-top-color: #ef4444; }
            .hero-banner { border-radius: .85rem; color:#fff; background:linear-gradient(110deg,#315cef,#8542e9); padding:1.35rem 1.5rem; }
            @media(max-width: 820px) { :root { --sidebar-w: 0px; } .sidebar { display:none; } .main { margin-left:0; padding:1rem; padding-top:78px; } }
    </style>
    @stack('styles')
</head>
<body>
@auth
    @if(auth()->user()->isAdmin())
        <header class="topbar">
            <a class="topbar-brand text-decoration-none" href="{{ route('admin.dashboard') }}">Bayiinet <span class="text-muted fw-normal small">/ Yönetim Paneli</span></a>
            <div class="d-flex align-items-center gap-2">
                <a href="{{ route('admin.settings.index') }}" class="btn btn-sm btn-light d-none d-md-inline">Ayarlar</a>
                <span class="text-muted small d-none d-sm-inline">{{ auth()->user()->name }}</span>
                <form action="{{ route('logout') }}" method="POST">@csrf<button class="btn btn-sm btn-outline-secondary">Çıkış</button></form>
            </div>
        </header>
    @endif
    <div class="sidebar">
        <div class="brand">{{ auth()->user()->isAdmin() ? 'İSTANBUL · BAYİ' : 'Bayiinet' }}<div class="small text-muted fw-normal">B2B / ENTEGRASYON</div></div>
        <nav class="mt-3">
            @if(auth()->user()->isAdmin())
                <a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                    <i class="bi bi-grid me-2"></i> Panel
                </a>
                <a href="{{ route('admin.orders.index') }}" class="{{ request()->routeIs('admin.orders.*') ? 'active' : '' }}"><i class="bi bi-bag-check me-2"></i> Siparişler</a>
                <a href="{{ route('admin.orders.index', ['status' => 'returned']) }}"><i class="bi bi-arrow-counterclockwise me-2"></i> İadeler & İptaller</a>
                <a href="{{ route('admin.customers.index') }}" class="{{ request()->routeIs('admin.customers.*') ? 'active' : '' }}"><i class="bi bi-person-lines-fill me-2"></i> Müşteriler</a>
                <div class="nav-section">Ürün</div>
                <a href="{{ route('admin.sources.index') }}" class="{{ request()->routeIs('admin.sources.*') ? 'active' : '' }}">
                    <i class="bi bi-file-earmark-code me-2"></i> XML Kaynakları
                </a>
                <a href="{{ route('admin.products.index') }}" class="{{ request()->routeIs('admin.products.*') ? 'active' : '' }}">
                    <i class="bi bi-box-seam me-2"></i> Ürün Havuzu
                </a>
                <a href="{{ route('admin.export.xml') }}"><i class="bi bi-box-arrow-up-right me-2"></i> Tek XML Çıkışı</a>
                <a href="{{ route('admin.products.critical') }}" class="{{ request()->routeIs('admin.products.critical') ? 'active' : '' }}"><i class="bi bi-exclamation-triangle me-2"></i> Kritik Stok</a>
                <a href="{{ route('admin.blacklist.index') }}" class="{{ request()->routeIs('admin.blacklist.*') ? 'active' : '' }}"><i class="bi bi-slash-circle me-2"></i> Kara Liste</a>
                <div class="nav-section">Satış</div>
                <a href="{{ route('admin.dealers.index') }}" class="{{ request()->routeIs('admin.dealers.*') ? 'active' : '' }}">
                    <i class="bi bi-people me-2"></i> Bayiler
                </a>
                <a href="{{ route('admin.sources.index') }}"><i class="bi bi-arrow-repeat me-2"></i> XML Aktarım Motoru</a>
                <a href="{{ route('admin.marketplace.index') }}" class="{{ request()->routeIs('admin.marketplace.*') ? 'active' : '' }}"><i class="bi bi-shop me-2"></i> Pazaryeri Yönetimi</a>
                <a href="{{ route('admin.announcements.index') }}" class="{{ request()->routeIs('admin.announcements.*') ? 'active' : '' }}"><i class="bi bi-megaphone me-2"></i> İlanlar</a>
                <a href="{{ route('admin.taxonomy.index') }}" class="{{ request()->routeIs('admin.taxonomy.*') ? 'active' : '' }}"><i class="bi bi-tags me-2"></i> Kategori & Marka</a>
                <a href="{{ route('admin.pricing.index') }}" class="{{ request()->routeIs('admin.pricing.*') ? 'active' : '' }}"><i class="bi bi-percent me-2"></i> Kâr & Fiyatlama</a>
                <div class="nav-section">Sistem</div>
                <a href="{{ route('admin.logs.index') }}" class="{{ request()->routeIs('admin.logs.*') ? 'active' : '' }}"><i class="bi bi-list-ul me-2"></i> Kayıtlar</a>
                <a href="{{ route('admin.settings.index') }}" class="{{ request()->routeIs('admin.settings.*') ? 'active' : '' }}"><i class="bi bi-sliders me-2"></i> Ayarlar</a>
            @else
                @if(auth()->user()->dealer?->isActive())
                    <a href="{{ route('home') }}"><i class="bi bi-house me-2"></i> Ana Sayfa</a>
                    <a href="{{ route('dealer.account') }}" class="{{ request()->routeIs('dealer.account') ? 'active' : '' }}">
                        <i class="bi bi-person me-2"></i> Hesabım
                    </a>
                    <a href="{{ route('dealer.dashboard') }}" class="{{ request()->routeIs('dealer.dashboard') ? 'active' : '' }}">
                        <i class="bi bi-speedometer2 me-2"></i> Dashboard
                    </a>
                    <a href="{{ route('dealer.products.index') }}" class="{{ request()->routeIs('dealer.products.*') ? 'active' : '' }}">
                        <i class="bi bi-box-seam me-2"></i> Ürünler
                    </a>
                    <a href="{{ route('dealer.orders.create') }}" class="{{ request()->routeIs('dealer.orders.create') ? 'active' : '' }}">
                        <i class="bi bi-plus-circle me-2"></i> Sipariş Ver
                    </a>
                    <a href="{{ route('dealer.orders.index') }}" class="{{ request()->routeIs('dealer.orders.index') || request()->routeIs('dealer.orders.show') ? 'active' : '' }}">
                        <i class="bi bi-cart-check me-2"></i> Siparişlerim
                    </a>
                @else
                    <a href="{{ route('dealer.application') }}" class="active">
                        <i class="bi bi-hourglass-split me-2"></i> Başvuru Durumu
                    </a>
                @endif
            @endif
            @unless(auth()->user()->isAdmin())
                <hr class="border-secondary mx-3">
                <a href="{{ route('logout') }}" onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                    <i class="bi bi-box-arrow-right me-2"></i> Çıkış
                </a>
                <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">@csrf</form>
            @endunless
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
        @yield('content')
    </div>
@else
    @yield('content')
@endauth
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
@stack('scripts')
</body>
</html>
