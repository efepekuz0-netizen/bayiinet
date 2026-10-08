@extends('layouts.app')
@section('title', 'Sipariş Ver')
@section('content')
@php
    $balance = (float) auth()->user()->dealer->balance;
    $unit = $selectedProduct
        ? (float) (($selectedProduct->sell_price ?? $selectedProduct->price) + (float) ($selectedVariant->price_diff ?? 0))
        : 0;
@endphp

<div class="mb-3">
    <a href="{{ $selectedProduct ? route('product.show', $selectedProduct) : route('home') }}" class="small text-decoration-none">&larr; Geri</a>
    <h4 class="mb-1 mt-1">Sipariş Ver</h4>
    <p class="text-muted small mb-0">Müşteri ve teslimat bilgilerini girin. Onayda tutar bakiyenizden düşülür.</p>
</div>

<div class="card border-0 shadow-sm mb-3 {{ $balance <= 0 ? 'border-danger' : '' }}">
    <div class="card-body d-flex justify-content-between align-items-center py-3">
        <div>
            <div class="text-muted small">Bakiyeniz</div>
            <div class="fs-4 fw-bold {{ $balance <= 0 ? 'text-danger' : 'text-success' }}">{{ number_format($balance, 2, ',', '.') }} ₺</div>
        </div>
        @if($selectedProduct)
            <div class="text-end">
                <div class="text-muted small">Birim fiyat</div>
                <div class="fw-semibold">{{ number_format($unit, 2, ',', '.') }} ₺</div>
            </div>
        @endif
    </div>
</div>

@if($balance <= 0)
    <div class="alert alert-warning">Bakiyeniz yetersiz. Sipariş için yöneticiye bakiye yüklemesi talep edin.</div>
@endif
@if($errors->any())
    <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

@if(!$selectedProduct)
    <div class="alert alert-info">
        Sipariş vermek için önce <a href="{{ route('home') }}">katalogdan</a> bir ürün seçip «Sipariş Ver»e tıklayın.
    </div>
@else
<form method="POST" action="{{ route('dealer.orders.store') }}" id="orderForm">
    @csrf
    <input type="hidden" name="items[0][product_id]" value="{{ $selectedProduct->id }}">
    @if($selectedVariant)
        <input type="hidden" name="items[0][product_variant_id]" value="{{ $selectedVariant->id }}">
    @endif

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="d-flex gap-3 align-items-start">
                @php $img = is_array($selectedProduct->images ?? null) ? ($selectedProduct->images[0] ?? null) : null; @endphp
                @if($img)
                    <img src="{{ $img }}" alt="" class="rounded" style="width:72px;height:72px;object-fit:cover">
                @endif
                <div class="flex-grow-1 min-w-0">
                    <div class="fw-semibold">{{ $selectedProduct->title }}</div>
                    <div class="small text-muted">{{ $selectedProduct->stock_code }}
                        @if($selectedVariant) · {{ $selectedVariant->full_name ?? $selectedVariant->sku }} @endif
                    </div>
                    <div class="small mt-1">Stok:
                        {{ $selectedVariant ? $selectedVariant->stock : $selectedProduct->stock }}
                    </div>
                </div>
            </div>
            <div class="row g-2 mt-3 align-items-end">
                <div class="col-6 col-md-3">
                    <label class="form-label">Adet *</label>
                    <input type="number" name="items[0][quantity]" id="qty" class="form-control" min="1"
                           max="{{ $selectedVariant ? $selectedVariant->stock : $selectedProduct->stock }}"
                           value="{{ old('items.0.quantity', 1) }}" required>
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label">Toplam</label>
                    <div class="form-control-plaintext fw-bold fs-5" id="lineTotal">{{ number_format($unit, 2, ',', '.') }} ₺</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold border-0 pt-3">Müşteri / Teslimat</div>
        <div class="card-body pt-0">
            <div class="mb-3">
                <label class="form-label">Ad Soyad *</label>
                <input type="text" name="customer_name" class="form-control form-control-lg" required
                       value="{{ old('customer_name') }}" autocomplete="name" placeholder="Alıcı adı soyadı">
            </div>
            <div class="mb-3">
                <label class="form-label">Telefon</label>
                <input type="tel" name="customer_phone" class="form-control" value="{{ old('customer_phone') }}"
                       placeholder="05xx xxx xx xx" autocomplete="tel">
            </div>
            <div class="row g-2">
                <div class="col-6 mb-3">
                    <label class="form-label">Şehir *</label>
                    <input type="text" name="customer_city" class="form-control" required value="{{ old('customer_city') }}">
                </div>
                <div class="col-6 mb-3">
                    <label class="form-label">İlçe</label>
                    <input type="text" name="customer_district" class="form-control" value="{{ old('customer_district') }}">
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Adres *</label>
                <textarea name="customer_address" class="form-control" rows="3" required
                          placeholder="Mahalle, sokak, bina, daire">{{ old('customer_address') }}</textarea>
            </div>
            <div class="mb-3">
                <label class="form-label">E-posta</label>
                <input type="email" name="customer_email" class="form-control" value="{{ old('customer_email') }}">
            </div>
            <div class="mb-0">
                <label class="form-label">Sipariş notu</label>
                <textarea name="dealer_note" class="form-control" rows="2" placeholder="Opsiyonel">{{ old('dealer_note') }}</textarea>
            </div>
        </div>
    </div>

    <div class="d-grid gap-2 mb-4">
        <button type="submit" class="btn btn-primary btn-lg" id="submitBtn" @disabled($balance <= 0)>
            Siparişi Onayla
        </button>
        <div class="text-center text-muted small">Kargo takip no sipariş oluştuktan sonra eklenir.</div>
    </div>
</form>
<script>
(function(){
    const unit = {{ json_encode($unit) }};
    const qty = document.getElementById('qty');
    const total = document.getElementById('lineTotal');
    const form = document.getElementById('orderForm');
    const btn = document.getElementById('submitBtn');
    function fmt(n){ return n.toLocaleString('tr-TR',{minimumFractionDigits:2,maximumFractionDigits:2})+' ₺'; }
    function upd(){ total.textContent = fmt(unit * (parseInt(qty.value,10)||0)); }
    qty.addEventListener('input', upd); upd();
    form.addEventListener('submit', function(){ btn.disabled = true; btn.textContent = 'Gönderiliyor…'; });
})();
</script>
@endif
@endsection
