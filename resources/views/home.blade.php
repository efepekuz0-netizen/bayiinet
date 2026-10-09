<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#2563eb">
    <title>Bayiinet — XML Bayilik & Stoksuz E-Ticaret</title>
    <meta name="description" content="Binlerce ürünü XML ile mağazanıza aktarın, stok tutmadan satın. Bayiinet XML bayilik platformu.">
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='7' fill='%232563eb'/%3E%3Ctext x='16' y='22' text-anchor='middle' font-family='sans-serif' font-weight='700' font-size='17' fill='white'%3EB%3C/text%3E%3C/svg%3E">
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root { --brand: #2563eb; --ink: #0f172a; --muted: #64748b; --soft: #f1f5f9; }
        body { font-family: system-ui, -apple-system, 'Segoe UI', sans-serif; color: var(--ink); background: #fff; }
        .topbar { background: var(--ink); color: #fff; font-size: .8rem; padding: .4rem 0; }
        .navbar-main { border-bottom: 1px solid #e2e8f0; background: #fff; position: sticky; top: 0; z-index: 100; }
        .logo { font-weight: 800; font-size: 1.45rem; color: var(--ink); text-decoration: none; }
        .logo span { color: var(--brand); }
        .hero {
            background: linear-gradient(135deg, #eff6ff 0%, #f8fafc 50%, #eef2ff 100%);
            padding: 3.5rem 0 2.5rem;
        }
        .hero h1 { font-size: clamp(1.8rem, 4vw, 2.75rem); font-weight: 800; line-height: 1.15; }
        .hero-stat { background: #fff; border-radius: 12px; padding: 1rem 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,.06); }
        .cat-chip {
            display: inline-block; padding: .4rem .9rem; border-radius: 999px;
            background: #fff; border: 1px solid #e2e8f0; color: var(--ink);
            text-decoration: none; font-size: .85rem; margin: .25rem;
            transition: .15s;
        }
        .cat-chip:hover, .cat-chip.active { background: var(--brand); color: #fff; border-color: var(--brand); }
        .section-title { font-weight: 800; font-size: 1.35rem; }
        .feature-box { padding: 1.5rem; border-radius: 14px; background: var(--soft); height: 100%; }
        .feature-box i { font-size: 1.6rem; color: var(--brand); }
        footer { background: var(--ink); color: #94a3b8; padding: 2.5rem 0; margin-top: 3rem; }
        footer a { color: #cbd5e1; text-decoration: none; }
    
        /* iOS'ta otomatik yakınlaştırmayı önlemek için input'lar en az 16px */
        .form-control { font-size: 16px; }
        .navbar-main { padding: .65rem 0; }
        .navbar-main .container { flex-wrap: wrap; }
        .mobile-search { display: none; width: 100%; margin-top: .65rem; }
        @media (max-width: 767.98px) {
            /* Mobilde kategori listesi yatay kaydırmalı şerit olur */
            .cat-scroll {
                display: flex; flex-wrap: nowrap; overflow-x: auto; gap: .35rem;
                padding-bottom: .35rem; -webkit-overflow-scrolling: touch;
                scrollbar-width: none;
            }
            .cat-scroll::-webkit-scrollbar { display: none; }
            .cat-scroll .cat-chip { flex: 0 0 auto; white-space: nowrap; }
            .topbar { font-size: .72rem; }
            .hero { padding: 1.75rem 0 1.25rem; }
            .hero h1 { font-size: 1.55rem; }
            .hero .btn { width: 100%; margin-bottom: .4rem; }
            .hero .d-flex.gap-2 { flex-direction: column; }
            .mobile-search { display: block; }
            .product-card .pc-price { font-size: 1rem; }
            .section-title { font-size: 1.15rem; }
            footer { padding: 1.75rem 0; margin-top: 2rem; }
            .feature-box { padding: 1rem; }
        }
        @media (min-width: 768px) {
            .mobile-search { display: none !important; }
        }
</style>
    @include('partials.product-card-styles')
</head>
<body class="@auth @if(auth()->user()->isDealer() && auth()->user()->dealer?->isActive()) has-bottom-nav @endif @endauth">
<div class="topbar">
    <div class="container d-flex justify-content-between">
        <span><i class="bi bi-truck me-1"></i> Stoksuz satış · XML ile otomatik güncelleme · Aynı gün kargo desteği</span>
        <span class="d-none d-md-inline">Destek: destek@bayiinet.com</span>
    </div>
</div>

<nav class="navbar-main py-3">
    <div class="container d-flex align-items-center justify-content-between gap-3">
        <a href="{{ route('home') }}" class="logo">Bayi<span>inet</span></a>
        <form action="{{ route('home') }}" method="GET" class="flex-grow-1 mx-lg-4 d-none d-md-block" style="max-width:480px">
            <div class="input-group">
                <input type="text" name="q" class="form-control" placeholder="Ürün, marka veya stok kodu ara..." value="{{ request('q') }}">
                <button class="btn btn-primary"><i class="bi bi-search"></i></button>
            </div>
        </form>
        <div class="d-flex gap-2 flex-shrink-0">
            @auth
                @if(auth()->user()->isAdmin())
                    <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-primary btn-sm">Admin</a>
                @elseif(auth()->user()->isDealer())
                    <a href="{{ route('dealer.account') }}" class="btn btn-outline-primary btn-sm">Hesabım</a>
                @endif
            @else
                <a href="{{ route('login') }}" class="btn btn-outline-secondary btn-sm">Giriş</a>
                <a href="{{ route('register') }}" class="btn btn-primary btn-sm d-none d-sm-inline-flex">Başvuru</a>
                <a href="{{ route('register') }}" class="btn btn-primary btn-sm d-sm-none"><i class="bi bi-person-plus"></i></a>
            @endauth
        </div>
        <form action="{{ route('home') }}" method="GET" class="mobile-search">
            <div class="input-group">
                <input type="text" name="q" class="form-control" placeholder="Ürün, marka, stok kodu..." value="{{ request('q') }}">
                <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i></button>
            </div>
        </form>
    </div>
</nav>
@if(auth()->check() && auth()->user()->isDealer() && auth()->user()->dealer?->isActive())
<div class="border-bottom bg-white">
    <div class="container d-flex justify-content-start justify-content-md-center gap-3 gap-md-4 py-2 small overflow-auto" style="-webkit-overflow-scrolling:touch">
        <a href="{{ route('home') }}" class="text-decoration-none {{ request()->routeIs('home') ? 'fw-bold text-primary' : 'text-secondary' }}"><i class="bi bi-house me-1"></i>Ana Sayfa</a>
        <a href="#urunler" class="text-decoration-none text-secondary"><i class="bi bi-box-seam me-1"></i>Ürünler</a>
        <a href="{{ route('dealer.orders.index') }}" class="text-decoration-none {{ request()->routeIs('dealer.orders.*') ? 'fw-bold text-primary' : 'text-secondary' }}"><i class="bi bi-receipt me-1"></i>Siparişler</a>
        <a href="{{ route('dealer.account') }}" class="text-decoration-none {{ request()->routeIs('dealer.account') ? 'fw-bold text-primary' : 'text-secondary' }}"><i class="bi bi-person me-1"></i>Hesabım</a>
    </div>
</div>
@endif

<section class="hero">
    <div class="container">
        <div class="row align-items-center g-4">
            <div class="col-lg-7">
                <span class="badge text-bg-primary mb-2">XML Bayilik Platformu</span>
                <h1>Stok tutmadan sat,<br>biz gönderelim.</h1>
                <p class="text-secondary mt-3 mb-4" style="max-width:520px">
                    Binlerce ürünü XML ile mağazanıza aktarın. Sipariş geldiğinde kargo etiketini paylaşın, ödemeyi yapın — ürün müşterinize gitsin.
                </p>
                <div class="d-flex gap-2 flex-wrap">
                    <a href="{{ route('register') }}" class="btn btn-primary btn-lg">Ücretsiz Bayi Ol</a>
                    <a href="#urunler" class="btn btn-outline-dark btn-lg">Ürünleri İncele</a>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="row g-3">
                    <div class="col-6"><div class="hero-stat"><div class="text-muted small">Aktif Ürün</div><div class="fs-4 fw-bold">{{ number_format($products->total()) }}+</div></div></div>
                    <div class="col-6"><div class="hero-stat"><div class="text-muted small">Kategori</div><div class="fs-4 fw-bold">{{ $categories->count() }}</div></div></div>
                    <div class="col-6"><div class="hero-stat"><div class="text-muted small">XML Senkron</div><div class="fs-4 fw-bold">Saatlik</div></div></div>
                    <div class="col-6"><div class="hero-stat"><div class="text-muted small">Bayilik Ücreti</div><div class="fs-4 fw-bold text-success">0 ₺</div></div></div>
                </div>
            </div>
        </div>
    </div>
</section>

<div class="container py-4">
    <div class="d-flex flex-wrap align-items-center gap-1 mb-2 cat-scroll">
        <a href="{{ route('home') }}" class="cat-chip {{ !request('kategori') ? 'active' : '' }}">Tümü</a>
        @foreach($categories as $cat)
            <a href="{{ route('home', ['kategori' => $cat, 'q' => request('q')]) }}"
               class="cat-chip {{ request('kategori') === $cat ? 'active' : '' }}">{{ $cat }}</a>
        @endforeach
    </div>
</div>

@if($featured->count())
<section class="container pb-4">
    <h2 class="section-title mb-3"><i class="bi bi-star-fill text-warning me-1"></i> Öne Çıkanlar</h2>
    <div class="row g-3">
        @foreach($featured as $p)
            @include('partials.product-card', ['product' => $p])
        @endforeach
    </div>
</section>
@endif

<section class="container pb-5" id="urunler">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="section-title mb-0">Ürün Kataloğu</h2>
        <span class="text-muted small">{{ $products->total() }} ürün</span>
    </div>
    <div class="row g-3">
        @forelse($products as $p)
            @include('partials.product-card', ['product' => $p])
        @empty
            <div class="col-12 text-center text-muted py-5">
                <i class="bi bi-box display-4 d-block mb-2"></i>
                Henüz vitrinde ürün yok. Admin panelinden XML yükleyin.
            </div>
        @endforelse
    </div>
    <div class="mt-4 d-flex justify-content-center">{{ $products->withQueryString()->links() }}</div>
</section>

<section class="container pb-5">
    <h2 class="section-title text-center mb-4">Neden Bayiinet?</h2>
    <div class="row g-3">
        <div class="col-md-3"><div class="feature-box"><i class="bi bi-file-earmark-code"></i><h6 class="mt-2 fw-bold">Ücretsiz XML</h6><p class="text-muted small mb-0">Bayilik ve XML ücreti yok. Anlık stok-fiyat senkronu.</p></div></div>
        <div class="col-md-3"><div class="feature-box"><i class="bi bi-truck"></i><h6 class="mt-2 fw-bold">Siz sat, biz gönder</h6><p class="text-muted small mb-0">Siparişte etiket + ödeme; ürünü biz kargolarız.</p></div></div>
        <div class="col-md-3"><div class="feature-box"><i class="bi bi-percent"></i><h6 class="mt-2 fw-bold">Esnek kar oranları</h6><p class="text-muted small mb-0">Kendi pazaryeri karınızı siz belirlersiniz.</p></div></div>
        <div class="col-md-3"><div class="feature-box"><i class="bi bi-arrow-repeat"></i><h6 class="mt-2 fw-bold">Saatlik güncelleme</h6><p class="text-muted small mb-0">Tüm bayilere otomatik feed yenileme.</p></div></div>
    </div>
</section>

<footer>
    <div class="container">
        <div class="row">
            <div class="col-md-4 mb-3">
                <div class="logo text-white mb-2">Bayi<span>inet</span></div>
                <p class="small">XML bayilik ile stoksuz e-ticaret platformu.</p>
            </div>
            <div class="col-md-4 mb-3">
                <h6 class="text-white">Hızlı Linkler</h6>
                <a href="{{ route('register') }}" class="d-block small">Bayilik Başvurusu</a>
                <a href="{{ route('login') }}" class="d-block small">Bayi Girişi</a>
            </div>
            <div class="col-md-4 mb-3">
                <h6 class="text-white">İletişim</h6>
                <p class="small mb-0">destek@bayiinet.com</p>
            </div>
        </div>
        <hr class="border-secondary">
        <div class="small text-center">© {{ date('Y') }} Bayiinet. Tüm hakları saklıdır.</div>
    </div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<div class="dealer-bottom-nav d-md-none">
    @auth
        @if(auth()->user()->isDealer() && auth()->user()->dealer?->isActive())
            <a href="{{ route('home') }}"><i class="bi bi-house"></i><span>Ana Sayfa</span></a>
            <a href="#urunler"><i class="bi bi-box-seam"></i><span>Ürünler</span></a>
            <a href="{{ route('dealer.orders.index') }}"><i class="bi bi-receipt"></i><span>Siparişler</span></a>
            <a href="{{ route('dealer.account') }}"><i class="bi bi-person"></i><span>Hesabım</span></a>
        @endif
    @endauth
</div>
<style>
.dealer-bottom-nav{position:fixed;bottom:0;left:0;right:0;background:#fff;border-top:1px solid #e2e8f0;z-index:1050;display:none;justify-content:space-around;padding:.4rem 0 calc(.4rem + env(safe-area-inset-bottom));box-shadow:0 -3px 14px rgba(15,23,42,.08)}
.dealer-bottom-nav a{color:#64748b;text-decoration:none;text-align:center;font-size:.68rem;display:flex;flex-direction:column;align-items:center;gap:.12rem;padding:.25rem .5rem;min-width:64px;min-height:48px;justify-content:center}
.dealer-bottom-nav i{font-size:1.2rem}.dealer-bottom-nav a:hover,.dealer-bottom-nav a.active{color:#2563eb}
@media(max-width:767.98px){.dealer-bottom-nav{display:flex}body.has-bottom-nav{padding-bottom:72px}}
</style>
</body>
</html>
