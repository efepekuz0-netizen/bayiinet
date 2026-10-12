@extends('layouts.app')
@section('title', 'XML Kaynağı Ayarları')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4"><h4>{{ $source->name }} · XML ayarları</h4><a href="{{ route('admin.sources.index') }}" class="btn btn-link">Geri dön</a></div>
<div class="card" style="max-width:820px"><div class="card-body">
<form method="POST" action="{{ route('admin.sources.update', $source) }}">@csrf @method('PUT')
<div class="row g-3">
<div class="col-md-6"><label class="form-label">Kaynak adı</label><input name="name" class="form-control" value="{{ old('name', $source->name) }}" required></div>
<div class="col-md-6"><label class="form-label">XML URL</label><input name="url" type="url" class="form-control" value="{{ old('url', $source->url) }}"></div>
<div class="col-md-3"><label class="form-label">XML kâr %</label><input name="xml_margin_percent" type="number" step="0.01" min="0" max="500" class="form-control" value="{{ old('xml_margin_percent', $source->xml_margin_percent) }}"></div>
<div class="col-md-3"><label class="form-label">Minimum kâr %</label><input name="min_margin_percent" type="number" step="0.01" min="0" max="500" class="form-control" value="{{ old('min_margin_percent', $source->min_margin_percent) }}"></div>
<div class="col-md-3"><label class="form-label">XML KDV %</label><input name="tax_rate" type="number" step="0.01" min="0" max="100" class="form-control" value="{{ old('tax_rate', $source->tax_rate) }}"></div>
<div class="col-md-3"><label class="form-label">Öncelik</label><input name="priority" type="number" min="1" max="100" class="form-control" value="{{ old('priority', $source->priority) }}" required></div>
</div>
<div class="form-check mt-3"><input type="checkbox" name="prices_include_tax" value="1" class="form-check-input" id="includeTax" @checked(old('prices_include_tax', $source->prices_include_tax))><label class="form-check-label" for="includeTax">XML alış fiyatları KDV dahil</label></div>
<div class="form-check mt-2"><input type="checkbox" name="recalculate" value="1" class="form-check-input" id="recalc" checked><label class="form-check-label" for="recalc">Bu kaynağın ürün fiyatlarını şimdi yeniden hesapla</label></div>

<div class="border rounded p-3 mt-3 bg-light">
    <div class="fw-semibold mb-2">Trendyol varsayılanları (bu XML için)</div>
    <p class="small text-muted mb-2">Ürün markası Trendyol’da bulunamazsa veya boşsa bu değerler kullanılır. Marka adını yazarsanız gönderimde XML markasının yerine de geçebilir.</p>
    <div class="row g-3">
        <div class="col-md-4">
            <label class="form-label">Varsayılan Marka No</label>
            <input name="default_trendyol_brand_id" type="number" min="1" class="form-control" value="{{ old('default_trendyol_brand_id', $source->default_trendyol_brand_id) }}" placeholder="örn. 2613880">
        </div>
        <div class="col-md-4">
            <label class="form-label">Varsayılan Marka Adı</label>
            <input name="default_trendyol_brand_name" class="form-control" value="{{ old('default_trendyol_brand_name', $source->default_trendyol_brand_name) }}" placeholder="XML markası boşsa / zorla">
        </div>
        <div class="col-md-4">
            <label class="form-label">Varsayılan Kategori No</label>
            <input name="default_trendyol_category_id" type="number" min="1" class="form-control" value="{{ old('default_trendyol_category_id', $source->default_trendyol_category_id) }}" placeholder="Boş = otomatik eşle">
        </div>
    </div>
</div>

<button class="btn btn-primary mt-4">Kaydet ve uygula</button>
</form></div></div>
@endsection
