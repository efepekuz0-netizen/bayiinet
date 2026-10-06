@extends('layouts.app')
@section('title', 'Ürün Düzenle')
@section('content')
<h4 class="mb-4">Ürün Düzenle</h4>
<div class="card" style="max-width:640px">
    <div class="card-body">
        <form method="POST" action="{{ route('admin.products.update', $product) }}">
            @csrf @method('PUT')
            <div class="mb-3">
                <label class="form-label">Stok Kodu</label>
                <input type="text" class="form-control" value="{{ $product->stock_code }}" disabled>
            </div>
            <div class="mb-3">
                <label class="form-label">Başlık</label>
                <input type="text" name="title" class="form-control" value="{{ old('title', $product->title) }}" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Marka</label>
                <input type="text" name="brand" class="form-control" value="{{ old('brand', $product->brand) }}">
            </div>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Maliyet (XML alış)</label>
                    <input type="number" step="0.01" name="cost_price" class="form-control" value="{{ old('cost_price', $product->cost_price ?? $product->price) }}">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">XML Kar %</label>
                    <input type="number" step="0.1" name="xml_margin_percent" class="form-control" value="{{ old('xml_margin_percent', $product->xml_margin_percent ?? 15) }}">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Min. Kar %</label>
                    <input type="number" step="0.1" name="min_margin_percent" class="form-control" value="{{ old('min_margin_percent', $product->min_margin_percent ?? 5) }}">
                </div>
            </div>
            <div class="alert alert-light border small">
                Bayi fiyatı (hesaplanan): <strong>{{ number_format($product->sell_price ?? $product->price, 2) }} ₺</strong>
            </div>
            <div class="mb-3">
                <label class="form-label">Stok</label>
                <input type="number" name="stock" class="form-control" value="{{ old('stock', $product->stock) }}" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Açıklama</label>
                <textarea name="description" class="form-control" rows="3">{{ old('description', $product->description) }}</textarea>
            </div>
            <div class="form-check mb-2">
                <input type="checkbox" name="is_active" value="1" class="form-check-input" id="active" {{ $product->is_active ? 'checked' : '' }}>
                <label class="form-check-label" for="active">Aktif</label>
            </div>
            <div class="form-check mb-2">
                <input type="checkbox" name="show_on_homepage" value="1" class="form-check-input" id="home" {{ ($product->show_on_homepage ?? true) ? 'checked' : '' }}>
                <label class="form-check-label" for="home">Anasayfada göster</label>
            </div>
            <div class="form-check mb-3">
                <input type="checkbox" name="is_featured" value="1" class="form-check-input" id="feat" {{ ($product->is_featured ?? false) ? 'checked' : '' }}>
                <label class="form-check-label" for="feat">Öne çıkan</label>
            </div>
            <button type="submit" class="btn btn-primary">Kaydet</button>
            <a href="{{ route('admin.products.index') }}" class="btn btn-link">Geri</a>
        </form>
    </div>
</div>
@endsection
