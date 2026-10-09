@extends('layouts.app')
@section('title', $product->title)
@push('styles')
<style>
    .pd-gallery { background:#f8fafc; border-radius:16px; border:1px solid #e8edf5; overflow:hidden; }
    .pd-gallery .main-img { width:100%; height:min(480px,70vw); object-fit:contain; background:#f8fafc; display:block; }
    .pd-thumbs { display:flex; gap:.5rem; padding:.75rem; overflow-x:auto; }
    .pd-thumbs img { width:72px; height:72px; object-fit:cover; border-radius:10px; border:2px solid transparent; cursor:pointer; background:#fff; }
    .pd-thumbs img.active, .pd-thumbs img:hover { border-color:#2563eb; }
    .pd-price { color:#2563eb; font-weight:800; font-size:1.75rem; }
    @media (max-width: 575.98px) {
        .pd-price { font-size: 1.4rem; }
        .pd-gallery .main-img { height: min(320px, 70vw); }
        .pd-thumbs img { width: 56px; height: 56px; }
        h1.h3 { font-size: 1.15rem; }
    }
</style>
@endpush
@section('content')
<div class="container-fluid px-0 px-md-2 py-3">
    <a href="{{ url()->previous() !== url()->current() ? url()->previous() : route('home') }}" class="text-decoration-none small text-muted">
        <i class="bi bi-arrow-left"></i> Geri
    </a>

    <div class="row g-4 mt-1">
        <div class="col-lg-5">
            @php
                $images = is_array($product->images ?? null) ? array_values(array_filter($product->images)) : [];
                $main = $images[0] ?? null;
            @endphp
            <div class="pd-gallery">
                @if($main)
                    <img src="{{ $main }}" alt="{{ $product->title }}" class="main-img" id="pdMainImg"
                         onerror="this.onerror=null; this.src='data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' width=\'400\' height=\'400\'><rect width=\'400\' height=\'400\' fill=\'%23f8fafc\'/><text x=\'200\' y=\'205\' text-anchor=\'middle\' font-family=\'sans-serif\' font-size=\'16\' fill=\'%2394a3b8\'>Görsel yüklenemedi</text></svg>';">
                    @if(count($images) > 1)
                        <div class="pd-thumbs">
                            @foreach($images as $i => $img)
                                <img src="{{ $img }}" class="{{ $i === 0 ? 'active' : '' }}" onclick="document.getElementById('pdMainImg').src=this.src; document.querySelectorAll('.pd-thumbs img').forEach(el=>el.classList.remove('active')); this.classList.add('active');" alt="">
                            @endforeach
                        </div>
                    @endif
                @else
                    <div class="d-flex align-items-center justify-content-center" style="height:360px">
                        <i class="bi bi-image fs-1 text-muted"></i>
                    </div>
                @endif
            </div>
        </div>

        <div class="col-lg-7">
            <div class="text-muted small text-uppercase" style="letter-spacing:.08em">{{ $product->brand ?: 'Bayiinet' }}</div>
            <h1 class="h3 mt-1 mb-3">{{ $product->title }}</h1>

            <div class="pd-price mb-1">{{ number_format((float) ($product->sell_price ?? $product->price), 2, ',', '.') }} ₺</div>
            <div class="text-muted small mb-3">
                Bayi alım fiyatı · KDV dahil
                @if($product->list_price)
                    · Liste: {{ number_format((float) $product->list_price, 2, ',', '.') }} ₺
                @endif
            </div>

            <div class="d-flex flex-wrap gap-2 mb-3">
                <span class="badge text-bg-{{ $product->effective_stock > 5 ? 'success' : ($product->effective_stock > 0 ? 'warning' : 'secondary') }}">
                    Stok: {{ $product->effective_stock }}
                </span>
                <span class="badge text-bg-light border">Kod: {{ $product->stock_code }}</span>
                @if($product->barcode)
                    <span class="badge text-bg-light border">Barkod: {{ $product->barcode }}</span>
                @endif
                @if($product->main_category)
                    <span class="badge text-bg-light border">{{ $product->main_category }}</span>
                @endif
            </div>

            @if($product->has_variants && $product->variants->isNotEmpty())
                <div class="mb-3">
                    <div class="fw-semibold small mb-1">Varyantlar</div>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered mb-0">
                            <thead><tr><th>Seçenek</th><th>Stok</th><th>Fiyat</th><th>Barkod</th></tr></thead>
                            <tbody>
                            @foreach($product->variants as $v)
                                <tr>
                                    <td>{{ $v->name }} {{ $v->value }}</td>
                                    <td>{{ $v->stock }}</td>
                                    <td>{{ number_format((float) ($v->variant_price ?? ((float) ($product->sell_price ?? $product->price) + (float) ($v->price_diff ?? 0))), 2, ',', '.') }} ₺</td>
                                    <td><code>{{ $v->barcode ?: '-' }}</code></td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            @auth
                @if(auth()->user()->isDealer() && auth()->user()->dealer?->isActive())
                    <a href="{{ route('dealer.orders.create', ['product' => $product->id]) }}" class="btn btn-primary btn-lg">
                        <i class="bi bi-cart-plus me-1"></i> Sipariş ver
                    </a>
                    <div class="text-muted small mt-2">Bakiyenizden düşülür · Mevcut bakiye: {{ number_format(auth()->user()->dealer->balance, 2, ',', '.') }} ₺</div>
                @elseif(auth()->user()->isDealer())
                    <a href="{{ route('dealer.application') }}" class="btn btn-outline-primary">Başvuru durumunu görüntüle</a>
                @elseif(auth()->user()->isAdmin())
                    <a href="{{ route('admin.products.edit', $product) }}" class="btn btn-outline-secondary btn-sm">Ürünü düzenle</a>
                @endif
            @else
                <a href="{{ route('login') }}?redirect={{ urlencode(route('dealer.orders.create', ['product' => $product->id])) }}" class="btn btn-primary btn-lg">
                    Giriş yapıp sipariş ver
                </a>
            @endauth

            @if($product->description)
                <div class="border-top pt-3 mt-4">
                    <h2 class="h6 fw-bold">Ürün açıklaması</h2>
                    <div class="text-secondary" style="line-height:1.7;white-space:pre-wrap">{{ $product->description }}</div>
                </div>
            @endif
        </div>
    </div>

    @if($related->isNotEmpty())
        <h2 class="h5 mt-5 mb-3">Benzer ürünler</h2>
        <div class="row g-3">
            @foreach($related as $relatedProduct)
                @include('partials.product-card', ['product' => $relatedProduct])
            @endforeach
        </div>
    @endif
</div>
@endsection
