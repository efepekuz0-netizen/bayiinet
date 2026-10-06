@extends('layouts.app')
@section('title', 'Kâr & Fiyatlama')
@section('content')
<h4 class="mb-1">Kâr & Fiyatlama Merkezi</h4>
<p class="text-muted mb-4">XML karı = sizin kârınız · Pazaryeri karı = bayinin kârı</p>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card border-primary h-100">
            <div class="card-body">
                <div class="text-primary small fw-semibold">XML KAR ORANI (Sizin)</div>
                <div class="display-6 fw-bold">%{{ $settings['xml_margin_percent'] }}</div>
                <p class="small text-muted mb-0">XML alış → Bayi satış fiyatı arasına eklenen payınız.</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted small fw-semibold">MİNİMUM KAR</div>
                <div class="display-6 fw-bold">%{{ $settings['min_margin_percent'] }}</div>
                <p class="small text-muted mb-0">Bu oranın altına düşülmez.</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-success h-100">
            <div class="card-body">
                <div class="text-success small fw-semibold">PAZARYERİ KAR (Bayi varsayılan)</div>
                <div class="display-6 fw-bold">%{{ $settings['default_marketplace_margin'] }}</div>
                <p class="small text-muted mb-0">Bayinin kendi satış fiyatına eklediği varsayılan marj.</p>
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header bg-white fw-semibold">Oranları Güncelle</div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.pricing.update') }}">
            @csrf @method('PUT')
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">XML Kar % (Sizin)</label>
                    <input type="number" step="0.1" name="xml_margin_percent" class="form-control" value="{{ $settings['xml_margin_percent'] }}" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Minimum Kar %</label>
                    <input type="number" step="0.1" name="min_margin_percent" class="form-control" value="{{ $settings['min_margin_percent'] }}" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Pazaryeri Kar % (Bayi)</label>
                    <input type="number" step="0.1" name="default_marketplace_margin" class="form-control" value="{{ $settings['default_marketplace_margin'] }}" required>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <div class="form-check mb-2">
                        <input type="checkbox" name="apply_to_all" value="1" class="form-check-input" id="applyAll" checked>
                        <label class="form-check-label" for="applyAll">Tüm ürünlere uygula</label>
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label">XML KDV %</label>
                    <input type="number" step="0.1" min="0" max="100" name="xml_tax_rate" class="form-control" value="{{ $settings['xml_tax_rate'] }}" required>
                    <div class="form-text">XML'de KDV alanı yoksa kullanılacak oran.</div>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <div class="form-check mb-2">
                        <input type="checkbox" name="xml_prices_include_tax" value="1" class="form-check-input" id="xmlPricesIncludeTax" @checked($settings['xml_prices_include_tax'] === '1')>
                        <label class="form-check-label" for="xmlPricesIncludeTax">XML alış fiyatı KDV dahil</label>
                    </div>
                </div>
            </div>
            <button class="btn btn-primary mt-3">
                <i class="bi bi-check2-circle me-1"></i> Kaydet & Uygula
            </button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header bg-white fw-semibold">Örnek fiyat önizleme (son ürünler)</div>
    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead><tr><th>Ürün</th><th>Maliyet</th><th>Kar %</th><th>Bayi Fiyatı</th><th>Bayi %20 ile satış</th></tr></thead>
            <tbody>
            @foreach($products as $p)
                @php
                    $cost = $p->cost_price ?? $p->price;
                    $sell = $p->sell_price ?? $p->price;
                    $retail = round($sell * 1.20, 2);
                @endphp
                <tr>
                    <td>{{ Str::limit($p->title, 40) }}</td>
                    <td>{{ number_format($cost, 2) }} ₺</td>
                    <td>%{{ number_format($p->xml_margin_percent ?? 0, 1) }}</td>
                    <td class="fw-bold">{{ number_format($sell, 2) }} ₺</td>
                    <td class="text-success">{{ number_format($retail, 2) }} ₺</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="card-footer">{{ $products->links() }}</div>
</div>
@endsection
