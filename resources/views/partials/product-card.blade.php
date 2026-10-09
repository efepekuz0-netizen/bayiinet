@php
    $images = is_array($product->images ?? null) ? array_values(array_filter($product->images)) : [];
    $img = $images[0] ?? null;
    // Not: images JSON içinde boş/bozuk değerler olabiliyor
    if (is_string($img) && trim($img) === '') {
        $img = null;
    }
    $price = (float) ($product->sell_price ?? $product->price ?? 0);
    $stock = (int) ($product->effective_stock ?? $product->stock ?? 0);
    // Bayi için önerilen perakende satış fiyatı (bayi kâr oranı uygulanmış)
    $retail = null;
    if (auth()->check() && auth()->user()->isDealer() && auth()->user()->dealer?->isActive()) {
        $retail = $product->priceForDealer(auth()->user()->dealer)['retail'] ?? null;
    }
    $stockClass = $stock > 5 ? 'text-bg-success' : ($stock > 0 ? 'text-bg-warning' : 'text-bg-secondary');
    $placeholder = "data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='300' height='240'><rect width='300' height='240' fill='%23f1f5f9'/><g fill='%2394a3b8'><rect x='118' y='88' width='64' height='48' rx='6'/><circle cx='132' cy='104' r='5'/><path d='M118 132l16-16 12 12 10-8 26 24z'/></g><text x='150' y='168' text-anchor='middle' font-family='sans-serif' font-size='13' fill='%2394a3b8'>Görsel yok</text></svg>";
@endphp
<div class="col-6 col-md-4 col-lg-3">
    <div class="product-card d-flex flex-column h-100">
        <a href="{{ route('product.show', $product) }}" class="text-decoration-none text-reset d-flex flex-column flex-grow-1">
            <div class="pc-img-wrap">
                @if($img)
                    <img src="{{ $img }}"
                         alt="{{ $product->title }}"
                         class="pc-img"
                         loading="lazy"
                         decoding="async"
                         onerror="this.onerror=null; this.classList.add('is-fallback'); this.src='{{ $placeholder }}';">
                @else
                    <div class="pc-img-placeholder"><i class="bi bi-image fs-1"></i></div>
                @endif
            </div>
            <div class="p-2 p-md-3 d-flex flex-column flex-grow-1">
                <div class="pc-brand text-truncate">{{ $product->brand ?: 'Bayiinet' }}</div>
                <h6 class="pc-title mt-1 mb-2">{{ Str::limit($product->title, 60) }}</h6>
                <div class="mt-auto">
                    <div class="d-flex justify-content-between align-items-center gap-1 flex-wrap">
                        <span class="pc-price">{{ number_format($price, 2, ',', '.') }} ₺</span>
                        <span class="badge pc-stock {{ $stockClass }}">Stok: {{ $stock }}</span>
                    </div>
                    @if($retail !== null && $retail > 0)
                        <div class="pc-code mt-1">Önerilen satış: <span class="fw-semibold text-body">{{ number_format($retail, 2, ',', '.') }} ₺</span></div>
                    @endif
                    <div class="pc-code text-truncate mt-1">{{ $product->stock_code }}</div>
                </div>
            </div>
        </a>
        @auth
            @if(auth()->user()->isDealer() && auth()->user()->dealer?->isActive())
                <div class="px-2 px-md-3 pb-3 mt-auto">
                    @if($stock > 0)
                        <a href="{{ route('dealer.orders.create', ['product' => $product->id]) }}" class="btn btn-primary btn-sm w-100">
                            <i class="bi bi-cart-plus me-1"></i> Sipariş ver
                        </a>
                    @else
                        <button type="button" class="btn btn-outline-secondary btn-sm w-100" disabled>Stokta yok</button>
                    @endif
                </div>
            @endif
        @else
            <div class="px-2 px-md-3 pb-3 mt-auto">
                <a href="{{ route('login') }}" class="btn btn-outline-primary btn-sm w-100">Giriş yapıp sipariş ver</a>
            </div>
        @endauth
    </div>
</div>
