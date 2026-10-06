@extends('layouts.app')
@section('title', 'Ürün Havuzu')
@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h4 class="mb-0">Ürün Havuzu</h4>
        <small class="text-muted">XML’den gelen tüm ürünler · Anasayfa vitrini buradan beslenir</small>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <form action="{{ route('admin.products.pull') }}" method="POST" onsubmit="return confirm('Tüm aktif kaynaklardan ürünler çekilsin mi?')">
            @csrf
            <button class="btn btn-primary btn-sm">
                <i class="bi bi-cloud-download me-1"></i> Ürünleri Çek / Güncelle
            </button>
        </form>
        <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#marginModal">
            <i class="bi bi-percent me-1"></i> Toplu Kar Oranı
        </button>
        <a href="{{ route('admin.pricing.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-sliders me-1"></i> Fiyat Ayarları
        </a>
    </div>
</div>

<form class="card card-body mb-3">
    <div class="row g-2 align-items-end">
        <div class="col-md-5">
            <label class="form-label small mb-1">Ara</label>
            <input type="text" name="q" class="form-control form-control-sm" placeholder="Başlık, stok kodu, barkod..." value="{{ request('q') }}">
        </div>
        <div class="col-md-3">
            <label class="form-label small mb-1">Durum</label>
            <select name="active" class="form-select form-select-sm">
                <option value="">Tümü</option>
                <option value="1" @selected(request('active')==='1')>Aktif</option>
                <option value="0" @selected(request('active')==='0')>Pasif</option>
            </select>
        </div>
        <div class="col-md-2">
            <button class="btn btn-sm btn-dark w-100">Filtrele</button>
        </div>
    </div>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover table-sm mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th>Stok</th>
                    <th>Ürün</th>
                    <th>Maliyet</th>
                    <th>XML Kar %</th>
                    <th>Bayi Fiyatı</th>
                    <th>Stok</th>
                    <th>Vitrin</th>
                    <th>Durum</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @foreach($products as $p)
                <tr>
                    <td><code class="small">{{ $p->stock_code }}</code></td>
                    <td>
                        <div class="fw-semibold" style="max-width:260px">{{ Str::limit($p->title, 45) }}</div>
                        <small class="text-muted">{{ $p->brand }} · {{ $p->source->name ?? '-' }}</small>
                    </td>
                    <td>{{ number_format($p->cost_price ?? $p->price, 2) }} ₺</td>
                    <td>
                        <span class="badge text-bg-light">%{{ number_format($p->xml_margin_percent ?? 0, 1) }}</span>
                    </td>
                    <td class="fw-bold text-primary">{{ number_format($p->sell_price ?? $p->price, 2) }} ₺</td>
                    <td>{{ $p->stock }}</td>
                    <td>
                        @if($p->show_on_homepage ?? true)
                            <i class="bi bi-eye text-success" title="Anasayfada"></i>
                        @else
                            <i class="bi bi-eye-slash text-muted"></i>
                        @endif
                        @if($p->is_featured ?? false)
                            <i class="bi bi-star-fill text-warning" title="Öne çıkan"></i>
                        @endif
                    </td>
                    <td>
                        @if($p->is_active)
                            <span class="badge bg-success">Aktif</span>
                        @else
                            <span class="badge bg-secondary">Pasif</span>
                        @endif
                    </td>
                    <td><a href="{{ route('admin.products.edit', $p) }}" class="btn btn-sm btn-outline-primary">Düzenle</a></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="card-footer">{{ $products->links() }}</div>
</div>

{{-- Toplu kar modal --}}
<div class="modal fade" id="marginModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" method="POST" action="{{ route('admin.products.bulk-margin') }}">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title">Toplu XML Kar Oranı</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small">Bu oran <strong>sizin karınız</strong>dır. XML alış fiyatı üzerine eklenir; çıkan fiyat bayilere ve anasayfaya yansır.</p>
                <label class="form-label">XML Kar Oranı (%)</label>
                <input type="number" step="0.1" min="0" name="xml_margin_percent" class="form-control" value="15" required>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">İptal</button>
                <button class="btn btn-primary">Tüm Ürünlere Uygula</button>
            </div>
        </form>
    </div>
</div>
@endsection
