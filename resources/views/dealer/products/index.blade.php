@extends('layouts.app')
@section('title', 'Ürünler')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h4 class="mb-0">Ürün Kataloğu</h4>
        <div class="text-muted small">Fiyatlar bayi alım fiyatınızdır. Bakiye: <strong>{{ number_format(auth()->user()->dealer->balance, 2, ',', '.') }} ₺</strong></div>
    </div>
    <form class="d-flex gap-2">
        <input type="text" name="q" class="form-control form-control-sm" placeholder="Ürün, marka, kod..." value="{{ request('q') }}" style="width:220px">
        <button class="btn btn-sm btn-primary">Ara</button>
    </form>
</div>

<div class="row g-3">
@if($products->isEmpty())
    <div class="col-12">
        <div class="alert alert-info mb-0">Aramanıza uygun ürün bulunamadı.</div>
    </div>
@endif
@foreach($products as $p)
    @php
        $price = (float) ($p->sell_price ?? $p->price ?? 0);
        $stock = $p->has_variants ? (int) $p->variants->sum('stock') : (int) $p->stock;
        $img = is_array($p->images ?? null) && count($p->images) ? $p->images[0] : null;
    @endphp
    <div class="col-6 col-md-4 col-lg-3">
        <div class="card h-100 shadow-sm border-0">
            <a href="{{ route('product.show', $p) }}" class="text-decoration-none text-reset">
                @if($img)
                    <img src="{{ $img }}" class="card-img-top" style="height:160px;object-fit:cover;background:#f1f5f9" alt="">
                @else
                    <div class="bg-light d-flex align-items-center justify-content-center" style="height:160px">
                        <i class="bi bi-image text-muted fs-1"></i>
                    </div>
                @endif
                <div class="card-body pb-2">
                    <div class="text-muted small text-truncate">{{ $p->brand ?: '—' }}</div>
                    <h6 class="card-title" style="font-size:.9rem;min-height:2.4em">{{ Str::limit($p->title, 48) }}</h6>
                    <p class="text-muted small mb-1">{{ $p->stock_code }}</p>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="fw-bold text-primary">{{ number_format($price, 2, ',', '.') }} ₺</span>
                        <span class="badge bg-{{ $stock > 0 ? 'success' : 'secondary' }}">Stok: {{ $stock }}</span>
                    </div>
                </div>
            </a>
            @if($stock > 0)
                <div class="card-footer bg-white border-0 pt-0">
                    <a href="{{ route('dealer.orders.create', ['product' => $p->id]) }}" class="btn btn-primary btn-sm w-100">
                        <i class="bi bi-cart-plus"></i> Sipariş ver
                    </a>
                </div>
            @endif
        </div>
    </div>
@endforeach
</div>
<div class="mt-4">{{ $products->links() }}</div>
@endsection
