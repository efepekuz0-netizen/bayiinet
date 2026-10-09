@extends('layouts.app')
@section('title', 'Ürünler')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h4 class="mb-0">Ürün Kataloğu</h4>
        <div class="text-muted small">
            Fiyatlar bayi alım fiyatınızdır · Önerilen satış, %{{ number_format((float) (auth()->user()->dealer->default_marketplace_margin ?? 20), 0) }} kâr oranınıza göre hesaplanır.
            Bakiye: <strong>{{ number_format(auth()->user()->dealer->balance, 2, ',', '.') }} ₺</strong>
        </div>
    </div>
    <form class="d-flex gap-2">
        <input type="text" name="q" class="form-control form-control-sm" placeholder="Ürün, marka, kod..." value="{{ request('q') }}" style="width:220px">
        <button class="btn btn-sm btn-primary">Ara</button>
    </form>
</div>

<div class="row g-3">
@forelse($products as $p)
    @include('partials.product-card', ['product' => $p])
@empty
    <div class="col-12">
        <div class="alert alert-info mb-0">
            <i class="bi bi-info-circle me-1"></i> Aramanıza uygun ürün bulunamadı.
        </div>
    </div>
@endforelse
</div>

<div class="mt-4">{{ $products->links() }}</div>
@endsection
