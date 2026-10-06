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
        <div class="p-3">
            <div class="text-muted small mb-1">{{ $product->brand ?: 'BayiXML' }}</div>
            <h6 class="mb-2" style="font-size:.9rem;line-height:1.35;min-height:2.5em">{{ Str::limit($product->title, 55) }}</h6>
            <div class="d-flex justify-content-between align-items-center">
                <span class="price">{{ number_format($product->sell_price ?? $product->price, 2) }} ₺</span>
                <span class="badge badge-stock {{ ($product->effective_stock ?? $product->stock) > 5 ? 'text-bg-success' : 'text-bg-warning' }}">
                    Stok: {{ $product->effective_stock ?? $product->stock }}
                </span>
            </div>
            <div class="small text-muted mt-1">{{ $product->stock_code }}</div>
        </div>
    </div>
</div>
