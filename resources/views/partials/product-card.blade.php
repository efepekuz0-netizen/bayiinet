@php
    $img = is_array($product->images ?? null) && count($product->images) ? $product->images[0] : null;
    $price = (float) ($product->sell_price ?? $product->price ?? 0);
    $stock = (int) ($product->effective_stock ?? $product->stock ?? 0);
@endphp
<div class="col-6 col-md-4 col-lg-3">
    <div class="product-card h-100 d-flex flex-column">
        <a href="{{ route('product.show', $product) }}" class="text-decoration-none text-reset flex-grow-1">
            @if($img)
                <img src="{{ $img }}" alt="{{ $product->title }}" loading="lazy" style="width:100%;height:180px;object-fit:cover;background:#f1f5f9;display:block">
            @else
                <div class="d-flex align-items-center justify-content-center bg-light" style="height:180px">
                    <i class="bi bi-image text-muted fs-1"></i>
                </div>
            @endif
            <div class="p-3">
                <div class="text-muted small mb-1 text-truncate">{{ $product->brand ?: 'Bayiinet' }}</div>
                <h6 class="mb-2" style="font-size:.9rem;line-height:1.35;min-height:2.5em">{{ Str::limit($product->title, 55) }}</h6>
                <div class="d-flex justify-content-between align-items-center gap-1">
                    <span class="price" style="color:#2563eb;font-weight:800">{{ number_format($price, 2, ',', '.') }} ₺</span>
                    <span class="badge {{ $stock > 5 ? 'text-bg-success' : ($stock > 0 ? 'text-bg-warning' : 'text-bg-secondary') }}" style="font-size:.72rem">
                        Stok: {{ $stock }}
                    </span>
                </div>
                <div class="small text-muted mt-1 text-truncate">{{ $product->stock_code }}</div>
            </div>
        </a>
        @auth
            @if(auth()->user()->isDealer() && auth()->user()->dealer?->isActive() && $stock > 0)
                <div class="px-3 pb-3 mt-auto">
                    <a href="{{ route('dealer.orders.create', ['product' => $product->id]) }}" class="btn btn-primary btn-sm w-100">
                        <i class="bi bi-cart-plus me-1"></i> Sipariş ver
                    </a>
                </div>
            @endif
        @else
            <div class="px-3 pb-3 mt-auto">
                <a href="{{ route('login') }}" class="btn btn-outline-primary btn-sm w-100">Giriş yapıp sipariş ver</a>
            </div>
        @endauth
    </div>
</div>
