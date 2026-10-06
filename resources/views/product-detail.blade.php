@extends('layouts.app')
@section('title', $product->title)
@section('content')
<div class="container py-4">
    <a href="{{ route('home') }}" class="text-decoration-none small"><i class="bi bi-arrow-left"></i> Ürünlere dön</a>
    <div class="row g-4 mt-1">
        <div class="col-md-5">
            @php $img = is_array($product->images ?? null) && count($product->images) ? $product->images[0] : null; @endphp
            @if($img)<img src="{{ $img }}" alt="{{ $product->title }}" class="img-fluid rounded shadow-sm w-100" style="max-height:480px;object-fit:contain;background:#f8fafc">@else<div class="bg-light rounded d-flex align-items-center justify-content-center" style="height:360px"><i class="bi bi-image fs-1 text-muted"></i></div>@endif
        </div>
        <div class="col-md-7">
            <div class="text-muted">{{ $product->brand ?: 'Bayiinet' }}</div>
            <h1 class="h3 mt-1">{{ $product->title }}</h1>
            <div class="h4 text-primary mt-3">{{ number_format($product->sell_price ?? $product->price, 2) }} ₺</div>
            <p class="text-muted">Stok kodu: {{ $product->stock_code }} · Stok: {{ $product->effective_stock }}</p>
            @if($product->description)<div class="border-top pt-3 mt-3">{!! nl2br(e($product->description)) !!}</div>@endif
            @auth
                @if(auth()->user()->isDealer() && auth()->user()->dealer?->isActive())
                    <a href="{{ route('dealer.orders.create', ['product' => $product->id]) }}" class="btn btn-primary btn-lg mt-4"><i class="bi bi-cart-plus me-1"></i> Sipariş ver</a>
                @elseif(auth()->user()->isDealer())
                    <a href="{{ route('dealer.application') }}" class="btn btn-outline-primary mt-4">Başvuru durumunu görüntüle</a>
                @endif
            @else
                <a href="{{ route('dealer.orders.create', ['product' => $product->id]) }}" class="btn btn-primary btn-lg mt-4">Giriş yapıp sipariş ver</a>
            @endauth
        </div>
    </div>
    @if($related->isNotEmpty())
        <h2 class="h5 mt-5 mb-3">Benzer ürünler</h2>
        <div class="row g-3">@foreach($related as $relatedProduct) @include('partials.product-card', ['product' => $relatedProduct]) @endforeach</div>
    @endif
</div>
@endsection
