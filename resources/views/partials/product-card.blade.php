<div class="col-6 col-md-4 col-lg-3">
    <div class="product-card">
        @php $img = is_array($product->images ?? null) && count($product->images) ? $product->images[0] : null; @endphp
        @if($img)
            <img src="{{ $img }}" alt="{{ $product->title }}" loading="lazy">
        @else
            <div class="d-flex align-items-center justify-content-center bg-light" style="height:200px">
                <i class="bi bi-image text-muted fs-1"></i>
            </div>
        @endif
        <a href="{{ route('product.show', $product) }}" class="text-decoration-none text-reset">
        <div class="p-3">
            <div class="text-muted small mb-1">{{ $product->brand ?: 'Bayiinet' }}</div>
            <h6 class="mb-2" style="font-size:.9rem;line-height:1.35;min-height:2.5em">{{ Str::limit($product->title, 55) }}</h6>
            <div class="d-flex justify-content-between align-items-center">
                <span class="price">{{ number_format($product->sell_price ?? $product->price, 2) }} ₺</span>
                <span class="badge badge-stock {{ ($product->effective_stock ?? $product->stock) > 5 ? 'text-bg-success' : 'text-bg-warning' }}">
                    Stok: {{ $product->effective_stock ?? $product->stock }}
                </span>
            </div>
            <div class="small text-muted mt-1">{{ $product->stock_code }}</div>
        </div>
        </a>
        @auth
            @if(auth()->user()->isDealer() && auth()->user()->dealer?->isActive())
                <div class="px-3 pb-3">
                    <a href="{{ route('dealer.orders.create', ['product' => $product->id]) }}" class="btn btn-primary btn-sm w-100">
                        <i class="bi bi-cart-plus me-1"></i> Sipariş ver
                    </a>
                </div>
            @endif
        @else
            <div class="px-3 pb-3">
                <a href="{{ route('dealer.orders.create', ['product' => $product->id]) }}" class="btn btn-outline-primary btn-sm w-100">Giriş yapıp sipariş ver</a>
            </div>
        @endauth
    </div>
</div>
