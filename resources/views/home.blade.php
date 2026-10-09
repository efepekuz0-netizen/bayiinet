<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#2563eb">
    <title>Bayiinet — XML Bayilik &amp; Stoksuz E-Ticaret</title>
    <meta name="description" content="Binlerce ürünü XML ile mağazanıza aktarın, stok tutmadan satın. Bayiinet XML bayilik platformu.">
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='7' fill='%232563eb'/%3E%3Ctext x='16' y='22' text-anchor='middle' font-family='sans-serif' font-weight='700' font-size='17' fill='white'%3EB%3C/text%3E%3C/svg%3E">
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root {
            --brand: #2563eb;
            --brand-dark: #1d4ed8;
            --brand-soft: #eff6ff;
            --accent: #7c3aed;
            --ink: #0f172a;
            --ink-2: #1e293b;
            --muted: #64748b;
            --line: #e6ecf5;
            --radius: 16px;
            --shadow-sm: 0 1px 2px rgba(15, 23, 42, .06), 0 4px 14px rgba(15, 23, 42, .05);
            --shadow-lg: 0 18px 40px rgba(15, 23, 42, .12);
        }

        * { -webkit-tap-highlight-color: transparent; }

        body {
            background: #f6f8fc;
            color: var(--ink-2);
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            -webkit-font-smoothing: antialiased;
        }

        a { text-decoration: none; }

        /* iOS'ta otomatik yakınlaştırmayı önlemek için input'lar en az 16px */
        .form-control, .form-select { font-size: 16px; }

        /* ---------- Üst bar ---------- */
        .topbar {
            background: var(--ink);
            color: #cbd5e1;
            font-size: .78rem;
            padding: .45rem 0;
        }
        .topbar a { color: #e2e8f0; }
        .topbar a:hover { color: #fff; }
        .topbar .bi { color: #60a5fa; }

        /* ---------- Navigasyon ---------- */
        .navbar-main {
            background: #fff;
            border-bottom: 1px solid var(--line);
            padding: .7rem 0;
            position: sticky;
            top: 0;
            z-index: 1040;
            box-shadow: 0 1px 12px rgba(15, 23, 42, .04);
        }
        .logo {
            font-weight: 800;
            font-size: 1.4rem;
            letter-spacing: -.5px;
            color: var(--ink);
        }
        .logo span { color: var(--brand); }
        .logo .dot {
            display: inline-block;
            width: 8px; height: 8px;
            background: var(--accent);
            border-radius: 50%;
            margin-left: 2px;
            vertical-align: middle;
        }
        .nav-search { max-width: 520px; }
        .nav-search .form-control { border-radius: 999px 0 0 999px; padding-left: 1rem; }
        .nav-search .btn { border-radius: 0 999px 999px 0; }
        .btn-brand {
            background: var(--brand);
            border-color: var(--brand);
            color: #fff;
            font-weight: 600;
        }
        .btn-brand:hover, .btn-brand:focus { background: var(--brand-dark); border-color: var(--brand-dark); color: #fff; }
        .btn-ghost {
            border: 1px solid var(--line);
            background: #fff;
            color: var(--ink-2);
            font-weight: 600;
        }
        .btn-ghost:hover { border-color: var(--brand); color: var(--brand); }

        /* ---------- Hero ---------- */
        .hero {
            position: relative;
            overflow: hidden;
            background:
                radial-gradient(1100px 420px at 12% -10%, rgba(124, 58, 237, .45), transparent 60%),
                radial-gradient(900px 400px at 92% 0%, rgba(37, 99, 235, .55), transparent 60%),
                linear-gradient(135deg, #0f172a 0%, #16254a 55%, #1e3a8a 100%);
            color: #fff;
            padding: 3.2rem 0 3.4rem;
        }
        .hero::after {
            content: "";
            position: absolute;
            left: -10%; right: -10%; bottom: -70px;
            height: 140px;
            background: #f6f8fc;
            border-radius: 50% 50% 0 0;
            opacity: .07;
        }
        .hero .container { position: relative; z-index: 1; }
        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: .4rem;
            background: rgba(255, 255, 255, .12);
            border: 1px solid rgba(255, 255, 255, .22);
            backdrop-filter: blur(6px);
            color: #e6edff;
            font-size: .78rem;
            font-weight: 600;
            border-radius: 999px;
            padding: .35rem .8rem;
            margin-bottom: 1rem;
        }
        .hero h1 {
            font-size: 2.6rem;
            line-height: 1.15;
            font-weight: 800;
            letter-spacing: -1px;
            margin-bottom: .85rem;
        }
        .hero h1 .grad {
            background: linear-gradient(92deg, #93c5fd, #c4b5fd);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        .hero .lead {
            color: #c7d5f5;
            font-size: 1.06rem;
            max-width: 620px;
            margin-bottom: 1.6rem;
        }
        .hero-search {
            background: #fff;
            border-radius: 999px;
            padding: .35rem;
            box-shadow: 0 14px 34px rgba(2, 10, 35, .35);
            max-width: 640px;
        }
        .hero-search .form-control,
        .hero-search .form-select {
            border: 0;
            box-shadow: none;
            background: transparent;
            padding: .65rem .9rem;
        }
        .hero-search .form-control:focus,
        .hero-search .form-select:focus { box-shadow: none; }
        .hero-search .form-select {
            max-width: 190px;
            border-left: 1px solid var(--line);
            color: var(--muted);
            cursor: pointer;
        }
        .hero-search .btn {
            border-radius: 999px;
            padding: .65rem 1.5rem;
            font-weight: 700;
        }
        .hero-stats { margin-top: 2rem; }
        .hero-stat {
            background: rgba(255, 255, 255, .08);
            border: 1px solid rgba(255, 255, 255, .16);
            backdrop-filter: blur(8px);
            border-radius: 14px;
            padding: .85rem 1rem;
            height: 100%;
        }
        .hero-stat .label {
            font-size: .72rem;
            text-transform: uppercase;
            letter-spacing: .6px;
            color: #a9bce6;
            margin-bottom: .15rem;
        }
        .hero-stat .value { font-size: 1.3rem; font-weight: 800; color: #fff; }
        .hero-points { margin-top: 1.4rem; display: flex; flex-wrap: wrap; gap: 1.25rem; }
        .hero-points span { color: #c7d5f5; font-size: .88rem; display: inline-flex; align-items: center; gap: .4rem; }
        .hero-points .bi { color: #6ee7b7; font-size: 1rem; }

        /* ---------- Kategori şeridi ---------- */
        .cat-bar { background: #fff; border-bottom: 1px solid var(--line); }
        .cat-scroll { display: flex; flex-wrap: wrap; gap: .4rem; }
        .cat-chip {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            background: #f1f5f9;
            color: #475569;
            border: 1px solid transparent;
            border-radius: 999px;
            padding: .4rem .85rem;
            font-size: .85rem;
            font-weight: 600;
            white-space: nowrap;
            transition: all .15s ease;
        }
        .cat-chip:hover { background: var(--brand-soft); color: var(--brand); }
        .cat-chip.active {
            background: var(--brand);
            color: #fff;
            box-shadow: 0 6px 16px rgba(37, 99, 235, .3);
        }

        /* ---------- Bölümler ---------- */
        .section-title {
            font-size: 1.3rem;
            font-weight: 800;
            letter-spacing: -.3px;
            color: var(--ink);
            display: flex;
            align-items: center;
            gap: .5rem;
        }
        .section-title .bi { color: var(--brand); }
        .section-sub { color: var(--muted); font-size: .92rem; }

        .feature-box {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: var(--radius);
            padding: 1.5rem 1.25rem;
            height: 100%;
            box-shadow: var(--shadow-sm);
            transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
        }
        .feature-box:hover { transform: translateY(-3px); box-shadow: var(--shadow-lg); border-color: #cddafc; }
        .feature-box .icon-wrap {
            width: 46px; height: 46px;
            border-radius: 13px;
            background: var(--brand-soft);
            color: var(--brand);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.35rem;
            margin-bottom: .85rem;
        }
        .feature-box h6 { font-weight: 700; color: var(--ink); margin-bottom: .35rem; }

        .step-card {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: var(--radius);
            padding: 1.4rem 1.25rem;
            height: 100%;
            position: relative;
            box-shadow: var(--shadow-sm);
        }
        .step-card .step-no {
            position: absolute;
            top: -14px; left: 18px;
            width: 30px; height: 30px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--brand), var(--accent));
            color: #fff;
            font-weight: 800;
            font-size: .85rem;
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 6px 14px rgba(37, 99, 235, .35);
        }
        .step-card h6 { font-weight: 700; color: var(--ink); margin: .5rem 0 .3rem; }

        .cta-band {
            background: linear-gradient(120deg, #2563eb 0%, #4f46e5 50%, #7c3aed 100%);
            border-radius: 22px;
            color: #fff;
            padding: 2.4rem 2rem;
            box-shadow: 0 20px 45px rgba(37, 99, 235, .28);
        }
        .cta-band h3 { font-weight: 800; letter-spacing: -.4px; }
        .cta-band p { color: #dbeafe; margin-bottom: 0; }
        .btn-light-brand {
            background: #fff;
            color: var(--brand-dark);
            font-weight: 700;
            border-radius: 999px;
            padding: .7rem 1.6rem;
        }
        .btn-light-brand:hover { background: #eef2ff; color: var(--brand-dark); }

        .empty-state {
            background: #fff;
            border: 1px dashed #cbd5e1;
            border-radius: var(--radius);
            padding: 3rem 1rem;
            color: var(--muted);
        }

        /* ---------- Sayfalama ---------- */
        .pagination { --bs-pagination-color: var(--brand); --bs-pagination-active-bg: var(--brand); --bs-pagination-active-border-color: var(--brand); --bs-pagination-border-radius: 10px; --bs-pagination-padding-x: .8rem; }
        .pagination .page-link { border-radius: 10px; margin: 0 2px; font-weight: 600; }

        /* ---------- Footer ---------- */
        footer { background: var(--ink); color: #94a3b8; padding: 2.75rem 0 1.5rem; margin-top: 3rem; }
        footer a { color: #cbd5e1; }
        footer a:hover { color: #fff; }
        footer .logo { color: #fff; font-size: 1.25rem; }
        footer h6 { color: #fff; font-weight: 700; font-size: .95rem; margin-bottom: .75rem; }

        /* ---------- Mobil alt menü ---------- */
        .dealer-bottom-nav {
            position: fixed; bottom: 0; left: 0; right: 0;
            background: #fff; border-top: 1px solid var(--line);
            z-index: 1050; display: none;
            justify-content: space-around;
            padding: .4rem 0 calc(.4rem + env(safe-area-inset-bottom));
            box-shadow: 0 -3px 14px rgba(15, 23, 42, .08);
        }
        .dealer-bottom-nav a {
            color: var(--muted); text-decoration: none; text-align: center;
            font-size: .68rem; display: flex; flex-direction: column;
            align-items: center; gap: .12rem; padding: .25rem .5rem;
            min-width: 64px; min-height: 48px; justify-content: center;
        }
        .dealer-bottom-nav i { font-size: 1.2rem; }
        .dealer-bottom-nav a:hover, .dealer-bottom-nav a.active { color: var(--brand); }

        .mobile-search { display: none; width: 100%; margin-top: .65rem; }

        @media (max-width: 991.98px) {
            .hero h1 { font-size: 2rem; }
        }
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
            .hero { padding: 2rem 0 2.25rem; }
            .hero h1 { font-size: 1.6rem; }
            .hero .lead { font-size: .95rem; }
            .hero-search { border-radius: 16px; padding: .4rem; }
            .hero-search .form-select { max-width: none; border-left: 0; border-top: 1px solid var(--line); }
            .hero-search .btn { width: 100%; }
            .hero-stats .hero-stat { padding: .7rem .8rem; }
            .hero-stat .value { font-size: 1.1rem; }
            .mobile-search { display: block; }
            .product-card .pc-price { font-size: 1rem; }
            .section-title { font-size: 1.12rem; }
            footer { padding: 1.75rem 0 1.25rem; margin-top: 2rem; }
            .feature-box { padding: 1.1rem; }
            .cta-band { padding: 1.75rem 1.25rem; border-radius: 18px; }
            .dealer-bottom-nav { display: flex; }
            body.has-bottom-nav { padding-bottom: 72px; }
            .hero-points { gap: .6rem 1rem; }
        }
        @media (min-width: 768px) {
            .mobile-search { display: none !important; }
        }
    </style>
    @include('partials.product-card-styles')
</head>
<body class="@auth @if(auth()->user()->isDealer() && auth()->user()->dealer?->isActive()) has-bottom-nav @endif @endauth">
<div class="topbar">
    <div class="container d-flex justify-content-between flex-wrap gap-1">
        <span><i class="bi bi-truck me-1"></i> Aynı gün kargo · Stoksuz satış · Saatlik XML senkronu</span>
        <span><i class="bi bi-envelope me-1"></i> <a href="mailto:destek@bayiinet.com">destek@bayiinet.com</a></span>
    </div>
</div>

<nav class="navbar-main">
    <div class="container">
        <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap w-100">
            <a href="{{ route('home') }}" class="logo">Bayi<span>inet</span><span class="dot"></span></a>

            <form action="{{ route('home') }}" method="GET" class="nav-search flex-grow-1 d-none d-md-block">
                <div class="input-group">
                    <input type="search" name="q" class="form-control" value="{{ request('q') }}"
                           placeholder="Ürün, marka veya stok kodu ara…" aria-label="Ürün ara">
                    <button class="btn btn-brand px-3" type="submit"><i class="bi bi-search"></i></button>
                </div>
            </form>

            <div class="d-flex align-items-center gap-2">
                @auth
                    @if(auth()->user()->isAdmin())
                        <a href="{{ route('admin.dashboard') }}" class="btn btn-ghost btn-sm"><i class="bi bi-speedometer2 me-1"></i> Yönetim</a>
                    @else
                        <a href="{{ route('dealer.dashboard') }}" class="btn btn-ghost btn-sm"><i class="bi bi-grid-1x2 me-1"></i> Panelim</a>
                    @endif
                    <form method="POST" action="{{ route('logout') }}" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-outline-secondary btn-sm">Çıkış</button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="btn btn-ghost btn-sm"><i class="bi bi-box-arrow-in-right me-1"></i> Giriş</a>
                    <a href="{{ route('register') }}" class="btn btn-brand btn-sm"><i class="bi bi-shop me-1"></i> Ücretsiz Bayi Ol</a>
                @endauth
                <button class="btn btn-ghost btn-sm d-md-none" type="button" data-bs-toggle="collapse" data-bs-target="#mobileSearch"
                        aria-expanded="false" aria-controls="mobileSearch" aria-label="Aramayı aç">
                    <i class="bi bi-search"></i>
                </button>
            </div>

            <div class="collapse mobile-search" id="mobileSearch">
                <form action="{{ route('home') }}" method="GET">
                    <div class="input-group">
                        <input type="search" name="q" class="form-control" value="{{ request('q') }}"
                               placeholder="Ürün ara…" aria-label="Ürün ara">
                        <button class="btn btn-brand px-3" type="submit"><i class="bi bi-search"></i></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</nav>

<section class="hero">
    <div class="container">
        <div class="row align-items-center g-4">
            <div class="col-lg-7">
                <span class="hero-badge"><i class="bi bi-lightning-charge-fill"></i> XML ile otomatik stok &amp; fiyat güncelleme</span>
                <h1>Stok tutmadan sat,<br><span class="grad">biz gönderelim.</span></h1>
                <p class="lead">Binlerce ürünü XML ile mağazanıza aktarın. Sipariş geldiğinde kargo etiketini paylaşın, ödemeyi yapın — ürün doğrudan müşterinize gitsin.</p>

                <form action="{{ route('home') }}" method="GET" class="hero-search d-flex align-items-center">
                    <i class="bi bi-search text-muted ms-3 d-none d-sm-block"></i>
                    <input type="search" name="q" class="form-control" value="{{ request('q') }}"
                           placeholder="Ürün, marka veya stok kodu ara…" aria-label="Ürün ara">
                    @if($categories->isNotEmpty())
                        <select name="kategori" class="form-select d-none d-sm-block" aria-label="Kategori">
                            <option value="">Tüm kategoriler</option>
                            @foreach($categories as $cat)
                                <option value="{{ $cat }}" @selected(request('kategori') === $cat)>{{ $cat }}</option>
                            @endforeach
                        </select>
                    @endif
                    <button class="btn btn-brand" type="submit">Ara</button>
                </form>

                <div class="hero-points">
                    <span><i class="bi bi-check-circle-fill"></i> Bayilik ücreti 0 ₺</span>
                    <span><i class="bi bi-check-circle-fill"></i> Saatlik otomatik senkron</span>
                    <span><i class="bi bi-check-circle-fill"></i> Kâr oranını sen belirle</span>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="row g-2 g-md-3 hero-stats">
                    <div class="col-6">
                        <div class="hero-stat">
                            <div class="label">Aktif ürün</div>
                            <div class="value">{{ number_format($products->total()) }}+</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="hero-stat">
                            <div class="label">Kategori</div>
                            <div class="value">{{ $categories->count() }}</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="hero-stat">
                            <div class="label">XML senkron</div>
                            <div class="value">Saatlik</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="hero-stat">
                            <div class="label">Bayilik ücreti</div>
                            <div class="value text-success">0 ₺</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<div class="cat-bar py-3">
    <div class="container">
        <div class="d-flex flex-wrap align-items-center gap-1 cat-scroll">
            <a href="{{ route('home', array_filter(['q' => request('q')])) }}"
               class="cat-chip {{ !request('kategori') ? 'active' : '' }}">
                <i class="bi bi-grid-3x3-gap"></i> Tümü
            </a>
            @foreach($categories as $cat)
                <a href="{{ route('home', array_filter(['kategori' => $cat, 'q' => request('q')])) }}"
                   class="cat-chip {{ request('kategori') === $cat ? 'active' : '' }}">{{ $cat }}</a>
            @endforeach
        </div>
    </div>
</div>

@if(!empty($error))
    <div class="container mt-3">
        <div class="alert alert-warning border-0 shadow-sm mb-0">
            <i class="bi bi-exclamation-triangle me-1"></i> {{ $error }}
        </div>
    </div>
@endif

@if($featured->isNotEmpty())
    <section class="container pt-4 pb-2">
        <div class="d-flex align-items-end justify-content-between mb-3">
            <div>
                <h2 class="section-title mb-1"><i class="bi bi-star-fill"></i> Öne çıkan ürünler</h2>
                <div class="section-sub">Vitrinde öne çıkardığımız seçili ürünler</div>
            </div>
        </div>
        <div class="row g-3">
            @foreach($featured as $p)
                @include('partials.product-card', ['product' => $p])
            @endforeach
        </div>
    </section>
@endif

<section class="container pt-4 pb-5" id="urunler">
    <div class="d-flex flex-wrap align-items-end justify-content-between gap-2 mb-3">
        <div>
            <h2 class="section-title mb-1"><i class="bi bi-box-seam"></i> Ürün kataloğu</h2>
            <div class="section-sub">
                @if(request('q') || request('kategori'))
                    Aramanıza uygun {{ number_format($products->total()) }} ürün listeleniyor
                @else
                    Toplam {{ number_format($products->total()) }} ürün · sayfa {{ $products->currentPage() }} / {{ max($products->lastPage(), 1) }}
                @endif
            </div>
        </div>
        @if(request('q') || request('kategori'))
            <a href="{{ route('home') }}" class="btn btn-ghost btn-sm"><i class="bi bi-x-circle me-1"></i> Filtreleri temizle</a>
        @endif
    </div>

    @if($products->count())
        <div class="row g-3">
            @foreach($products as $p)
                @include('partials.product-card', ['product' => $p])
            @endforeach
        </div>
        <div class="mt-4 d-flex justify-content-center">{{ $products->withQueryString()->links() }}</div>
    @else
        <div class="empty-state text-center">
            <i class="bi bi-search display-5 d-block mb-2 text-secondary"></i>
            <h6 class="fw-bold text-dark">Aradığınız kriterlere uygun ürün bulunamadı</h6>
            <p class="mb-3">Farklı bir anahtar kelime deneyin veya tüm kataloğu inceleyin.</p>
            <a href="{{ route('home') }}" class="btn btn-brand btn-sm">Tüm kataloğu gör</a>
        </div>
    @endif
</section>

<section class="container pb-5">
    <div class="text-center mb-4">
        <h2 class="section-title justify-content-center"><i class="bi bi-diagram-3"></i> Nasıl çalışır?</h2>
        <div class="section-sub">Üç adımda satışa başla</div>
    </div>
    <div class="row g-3">
        <div class="col-md-4">
            <div class="step-card">
                <span class="step-no">1</span>
                <h6 class="mt-2">Bayi ol</h6>
                <p class="text-muted small mb-0">Ücretsiz başvuruyu doldur, hesabın onaylandıktan sonra kataloğa anında eriş.</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="step-card">
                <span class="step-no">2</span>
                <h6 class="mt-2">Ürünleri aktar</h6>
                <p class="text-muted small mb-0">XML, JSON ya da Excel ile ürünleri mağazana çek; stok ve fiyatlar saatlik güncellenir.</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="step-card">
                <span class="step-no">3</span>
                <h6 class="mt-2">Sat, biz gönderelim</h6>
                <p class="text-muted small mb-0">Sipariş geldiğinde kargo etiketini paylaş, ödemeyi yap — ürünü biz kargolarız.</p>
            </div>
        </div>
    </div>
</section>

<section class="container pb-5">
    <div class="text-center mb-4">
        <h2 class="section-title justify-content-center"><i class="bi bi-shield-check"></i> Neden Bayiinet?</h2>
        <div class="section-sub">Stoksuz e-ticaret için ihtiyacın olan her şey</div>
    </div>
    <div class="row g-3">
        <div class="col-md-3 col-6">
            <div class="feature-box">
                <div class="icon-wrap"><i class="bi bi-file-earmark-code"></i></div>
                <h6>Ücretsiz XML</h6>
                <p class="text-muted small mb-0">Bayilik ve XML ücreti yok. Anlık stok-fiyat senkronu.</p>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="feature-box">
                <div class="icon-wrap"><i class="bi bi-truck"></i></div>
                <h6>Siz sat, biz gönder</h6>
                <p class="text-muted small mb-0">Siparişte etiket + ödeme; ürünü biz kargolarız.</p>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="feature-box">
                <div class="icon-wrap"><i class="bi bi-percent"></i></div>
                <h6>Esnek kâr oranları</h6>
                <p class="text-muted small mb-0">Kendi pazaryeri kâr oranını sen belirlersin.</p>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="feature-box">
                <div class="icon-wrap"><i class="bi bi-arrow-repeat"></i></div>
                <h6>Saatlik güncelleme</h6>
                <p class="text-muted small mb-0">Tüm bayilere otomatik feed yenileme.</p>
            </div>
        </div>
    </div>
</section>

<section class="container pb-5">
    <div class="cta-band d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div>
            <h3 class="mb-1">Stoksuz satışa bugün başla</h3>
            <p>Bayilik ücreti yok, XML ücreti yok. Dakikalar içinde kataloğu mağazana aktar.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('register') }}" class="btn btn-light-brand"><i class="bi bi-shop me-1"></i> Ücretsiz Bayi Ol</a>
            <a href="#urunler" class="btn btn-outline-light"><i class="bi bi-box-seam me-1"></i> Ürünleri İncele</a>
        </div>
    </div>
</section>

<footer>
    <div class="container">
        <div class="row g-4">
            <div class="col-md-4">
                <div class="logo text-white mb-2">Bayi<span>inet</span><span class="dot"></span></div>
                <p class="small mb-0">XML bayilik ile stoksuz e-ticaret platformu. Stok tutmadan sat, kargoyu biz gönderelim.</p>
            </div>
            <div class="col-md-4">
                <h6>Hızlı Linkler</h6>
                <a href="{{ route('register') }}" class="d-block small mb-1">Bayilik Başvurusu</a>
                <a href="{{ route('login') }}" class="d-block small mb-1">Bayi Girişi</a>
                <a href="{{ route('home') }}" class="d-block small mb-1">Ürün Kataloğu</a>
            </div>
            <div class="col-md-4">
                <h6>İletişim</h6>
                <p class="small mb-1"><i class="bi bi-envelope me-1"></i> destek@bayiinet.com</p>
                <p class="small mb-0"><i class="bi bi-clock-history me-1"></i> XML senkronu: her saat başı</p>
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
</body>
</html>
