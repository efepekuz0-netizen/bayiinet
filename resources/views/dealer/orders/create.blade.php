@extends('layouts.app')
@section('title', 'Sipariş Ver')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1">Yeni Sipariş</h4>
        <div class="text-muted small">Ürünler stok ve bayi bakiyesi kontrol edilerek sipariş edilir.</div>
    </div>
</div>

@if($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif

<form method="POST" action="{{ route('dealer.orders.store') }}" id="orderForm">
    @csrf
    <div class="row g-3">
        <div class="col-md-5">
            <div class="card mb-3">
                <div class="card-header bg-white fw-semibold">Teslimat Bilgileri</div>
                <div class="card-body">
                    <div class="mb-2">
                        <label class="form-label">Ad Soyad *</label>
                        <input type="text" name="customer_name" class="form-control" required value="{{ old('customer_name') }}">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Telefon</label>
                        <input type="text" name="customer_phone" class="form-control" value="{{ old('customer_phone') }}">
                    </div>
                    <div class="row">
                        <div class="col-6 mb-2">
                            <label class="form-label">Şehir *</label>
                            <input type="text" name="customer_city" class="form-control" required value="{{ old('customer_city') }}">
                        </div>
                        <div class="col-6 mb-2">
                            <label class="form-label">İlçe</label>
                            <input type="text" name="customer_district" class="form-control" value="{{ old('customer_district') }}">
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Adres *</label>
                        <textarea name="customer_address" class="form-control" rows="3" required>{{ old('customer_address') }}</textarea>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">E-posta</label>
                        <input type="email" name="customer_email" class="form-control" value="{{ old('customer_email') }}">
                    </div>
                    <div>
                        <label class="form-label">Sipariş Notu</label>
                        <textarea name="dealer_note" class="form-control" rows="2">{{ old('dealer_note') }}</textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-7">
            <div class="card mb-3">
                <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
                    <span>Ürünler</span>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="addRow" @disabled($products->isEmpty())>+ Ürün Ekle</button>
                </div>
                <div class="card-body">
                    @if($products->isEmpty())
                        <p class="text-muted mb-0">Şu anda sipariş edilebilir stokta ürün bulunmuyor.</p>
                    @else
                        <div id="items">
                            <div class="row g-2 mb-3 item-row">
                                <div class="col-8">
                                    <select name="items[0][product_id]" class="form-select product-select" required>
                                        <option value="">Ürün seçin...</option>
                                        @foreach($products as $product)
                                            @if($product->has_variants)
                                                @foreach($product->variants->where('stock', '>', 0) as $variant)
                                                    <option value="{{ $product->id }}" data-variant-id="{{ $variant->id }}" data-price="{{ (float) $product->price + (float) $variant->price_diff }}" data-stock="{{ $variant->stock }}">{{ $product->title }} — {{ $variant->full_name }} · {{ number_format((float) $product->price + (float) $variant->price_diff, 2) }} ₺ · Stok: {{ $variant->stock }}</option>
                                                @endforeach
                                            @elseif($product->stock > 0)
                                                <option value="{{ $product->id }}" data-price="{{ $product->price }}" data-stock="{{ $product->stock }}">{{ $product->title }} · {{ number_format($product->price, 2) }} ₺ · Stok: {{ $product->stock }}</option>
                                            @endif
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-3">
                                    <input type="number" name="items[0][quantity]" class="form-control qty" min="1" value="1" required>
                                </div>
                                <div class="col-1">
                                    <button type="button" class="btn btn-outline-danger btn-sm remove-row" aria-label="Ürünü kaldır">×</button>
                                </div>
                                <input type="hidden" name="items[0][product_variant_id]" class="variant-id">
                            </div>
                        </div>
                        <template id="product-options">
                            @foreach($products as $product)
                                @if($product->has_variants)
                                    @foreach($product->variants->where('stock', '>', 0) as $variant)
                                        <option value="{{ $product->id }}" data-variant-id="{{ $variant->id }}" data-price="{{ (float) $product->price + (float) $variant->price_diff }}" data-stock="{{ $variant->stock }}">{{ $product->title }} — {{ $variant->full_name }} · {{ number_format((float) $product->price + (float) $variant->price_diff, 2) }} ₺ · Stok: {{ $variant->stock }}</option>
                                    @endforeach
                                @elseif($product->stock > 0)
                                    <option value="{{ $product->id }}" data-price="{{ $product->price }}" data-stock="{{ $product->stock }}">{{ $product->title }} · {{ number_format($product->price, 2) }} ₺ · Stok: {{ $product->stock }}</option>
                                @endif
                            @endforeach
                        </template>
                        <template id="item-row-template">
                            <div class="row g-2 mb-3 item-row">
                                <div class="col-8">
                                    <select name="items[__INDEX__][product_id]" class="form-select product-select" required>
                                        <option value="">Ürün seçin...</option>
                                    </select>
                                </div>
                                <div class="col-3">
                                    <input type="number" name="items[__INDEX__][quantity]" class="form-control qty" min="1" value="1" required>
                                </div>
                                <div class="col-1">
                                    <button type="button" class="btn btn-outline-danger btn-sm remove-row" aria-label="Ürünü kaldır">×</button>
                                </div>
                                <input type="hidden" name="items[__INDEX__][product_variant_id]" class="variant-id">
                            </div>
                        </template>
                        <div class="d-flex justify-content-between border-top pt-3">
                            <span class="text-muted">Tahmini toplam</span>
                            <strong id="order-total">0,00 ₺</strong>
                        </div>
                    @endif
                </div>
            </div>
            <button type="submit" class="btn btn-primary btn-lg w-100" @disabled($products->isEmpty())>Siparişi Oluştur ve Bakiyeden Düş</button>
        </div>
    </div>
</form>
@endsection

@if($products->isNotEmpty())
    @push('scripts')
    <script>
    (() => {
        let index = 1;
        const items = document.getElementById('items');
        const productOptions = document.getElementById('product-options').innerHTML;
        const rowTemplate = document.getElementById('item-row-template');
        const totalElement = document.getElementById('order-total');

        const updateRow = (row) => {
            const select = row.querySelector('.product-select');
            const option = select.selectedOptions[0];
            const quantity = row.querySelector('.qty');
            row.querySelector('.variant-id').value = option?.dataset.variantId || '';
            quantity.max = option?.dataset.stock || '';
            updateTotal();
        };

        const updateTotal = () => {
            const total = [...items.querySelectorAll('.item-row')].reduce((sum, row) => {
                const option = row.querySelector('.product-select').selectedOptions[0];
                const quantity = Number(row.querySelector('.qty').value || 0);
                return sum + Number(option?.dataset.price || 0) * quantity;
            }, 0);
            totalElement.textContent = new Intl.NumberFormat('tr-TR', {
                style: 'currency',
                currency: 'TRY'
            }).format(total);
        };

        document.getElementById('addRow').addEventListener('click', () => {
            const row = rowTemplate.content.firstElementChild.cloneNode(true);
            row.querySelectorAll('[name]').forEach((field) => {
                field.name = field.name.replaceAll('__INDEX__', index);
            });
            row.querySelector('.product-select').insertAdjacentHTML('beforeend', productOptions);
            items.appendChild(row);
            index++;
        });

        items.addEventListener('change', (event) => {
            if (event.target.matches('.product-select')) {
                updateRow(event.target.closest('.item-row'));
            }
        });
        items.addEventListener('input', (event) => {
            if (event.target.matches('.qty')) {
                updateTotal();
            }
        });
        items.addEventListener('click', (event) => {
            if (event.target.matches('.remove-row') && items.querySelectorAll('.item-row').length > 1) {
                event.target.closest('.item-row').remove();
                updateTotal();
            }
        });
    })();
    </script>
    @endpush
@endif
