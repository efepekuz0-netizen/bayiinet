@extends('layouts.app')
@section('title', 'Ürünler')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0">Ürün Kataloğu</h4>
    <form class="d-flex gap-2">
        <input type="text" name="q" class="form-control form-control-sm" placeholder="Ürün ara..." value="{{ request('q') }}" style="width:220px">
        <button class="btn btn-sm btn-primary">Ara</button>
    </form>
</div>

<div class="row g-3">
@if($products->isEmpty())
    <div class="col-12">
        <div class="alert alert-info mb-0">
            Aramanıza uygun veya stokta bulunan ürün bulunamadı. Farklı bir ürün adı, marka, barkod ya da stok kodu deneyin.
        </div>
    </div>
@endif
@foreach($products as $p)
    <div class="col-md-3">
        <div class="card h-100">
            @if(!empty($p->images[0]))
                <img src="{{ $p->images[0] }}" class="card-img-top" style="height:160px;object-fit:cover" alt="">
            @else
                <div class="bg-light d-flex align-items-center justify-content-center" style="height:160px">
                    <i class="bi bi-image text-muted fs-1"></i>
                </div>
            @endif
            <div class="card-body">
                <h6 class="card-title">{{ Str::limit($p->title, 45) }}</h6>
                <p class="text-muted small mb-1">{{ $p->stock_code }}</p>
                <div class="d-flex justify-content-between align-items-center">
                    <span><span class="fw-bold text-primary">{{ number_format($p->price, 2) }} ₺</span><span class="d-block small text-muted">Önerilen satış: {{ number_format($p->list_price ?? ((float) $p->price * (1 + $profitMargin / 100)), 2) }} ₺</span></span>
                    @php($availableStock = $p->has_variants ? $p->variants->sum('stock') : $p->stock)
                    <span class="badge bg-{{ $availableStock > 0 ? 'success' : 'secondary' }}">Stok: {{ $availableStock }}</span>
                </div>
            </div>
        </div>
    </div>
@endforeach
</div>
<div class="mt-4">{{ $products->links() }}</div>
@endsection
