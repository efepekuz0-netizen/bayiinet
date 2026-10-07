@extends('layouts.app')
@section('title', $dealer->company_name)
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h4 class="mb-0">{{ $dealer->company_name }}</h4>
        <span class="badge bg-{{ $dealer->status === 'active' ? 'success' : ($dealer->status === 'pending' ? 'warning' : 'secondary') }}">
            {{ $dealer->status }}
        </span>
        @if($dealer->last_synced_at)
            <span class="text-muted small ms-2">Son XML aktarım: {{ $dealer->last_synced_at->format('d.m.Y H:i') }}</span>
        @endif
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('admin.dealers.trendyol', $dealer) }}" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-shop me-1"></i> Trendyol'a Ürün Gönder
        </a>
        @if($dealer->status === 'pending')
            <form method="POST" action="{{ route('admin.dealers.approve', $dealer) }}">@csrf
                <button class="btn btn-success btn-sm">Onayla</button>
            </form>
        @endif
        @if($dealer->status === 'active')
            <form method="POST" action="{{ route('admin.dealers.suspend', $dealer) }}">@csrf
                <button class="btn btn-warning btn-sm">Askıya Al</button>
            </form>
        @elseif($dealer->status === 'suspended')
            <form method="POST" action="{{ route('admin.dealers.approve', $dealer) }}">@csrf
                <button class="btn btn-success btn-sm">Tekrar Aktif Et</button>
            </form>
        @endif
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if($errors->any())
    <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card p-3 h-100">
            <div class="text-muted small">Bakiye</div>
            <div class="fs-3 fw-bold">{{ number_format($dealer->balance, 2) }} ₺</div>
            <form method="POST" action="{{ route('admin.dealers.balance', $dealer) }}" class="mt-2 d-flex gap-1">
                @csrf
                <input type="number" step="0.01" name="amount" class="form-control form-control-sm" placeholder="Tutar" required>
                <button class="btn btn-sm btn-primary">Ekle</button>
            </form>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card p-3 h-100">
            <div class="text-muted small">Bayiye özel XML</div>
            <code class="small d-block mt-1" style="word-break:break-all">{{ url('/xml/'.$dealer->xml_token.'.xml') }}</code>
            <div class="mt-2 small text-muted">
                Otomatik senkron: {{ $dealer->auto_sync_enabled ? 'Açık' : 'Kapalı' }} ·
                Kar %{{ number_format($dealer->default_marketplace_margin ?? 20, 1) }}
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card p-3 h-100">
            <div class="text-muted small">Yetkili / İletişim</div>
            <div>{{ $dealer->user->name ?? '-' }}</div>
            <div class="small">{{ $dealer->user->email ?? '' }}</div>
            <div>{{ $dealer->phone }}</div>
            <div>{{ $dealer->city }} / {{ $dealer->district }}</div>
        </div>
    </div>
</div>

{{-- Hesap, kar ve entegrasyon yönetimi --}}
<form method="POST" action="{{ route('admin.dealers.update', $dealer) }}" class="mb-4">
    @csrf @method('PUT')
    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header bg-white fw-semibold">Hesap bilgileri</div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-12">
                            <label class="form-label small">Firma adı</label>
                            <input name="company_name" class="form-control form-control-sm" value="{{ old('company_name', $dealer->company_name) }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small">Telefon</label>
                            <input name="phone" class="form-control form-control-sm" value="{{ old('phone', $dealer->phone) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small">Vergi no</label>
                            <input name="tax_number" class="form-control form-control-sm" value="{{ old('tax_number', $dealer->tax_number) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small">Vergi dairesi</label>
                            <input name="tax_office" class="form-control form-control-sm" value="{{ old('tax_office', $dealer->tax_office) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small">Şehir</label>
                            <input name="city" class="form-control form-control-sm" value="{{ old('city', $dealer->city) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small">İlçe</label>
                            <input name="district" class="form-control form-control-sm" value="{{ old('district', $dealer->district) }}">
                        </div>
                        <div class="col-12">
                            <label class="form-label small">Adres</label>
                            <textarea name="address" rows="2" class="form-control form-control-sm">{{ old('address', $dealer->address) }}</textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label small">Admin notu (sadece siz görürsünüz)</label>
                            <textarea name="admin_note" rows="2" class="form-control form-control-sm">{{ old('admin_note', $dealer->admin_note) }}</textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header bg-white fw-semibold">Kâr & otomatik XML aktarımı</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label small">Bayi pazaryeri kâr oranı %</label>
                        <input type="number" step="0.1" min="0" max="500" name="default_marketplace_margin"
                               class="form-control form-control-sm"
                               value="{{ old('default_marketplace_margin', $dealer->default_marketplace_margin ?? 20) }}">
                        <div class="form-text">
                            Bayinin XML’indeki <code>retail_price</code> = sizin satış fiyatı + bu yüzde.
                            Trendyol gönderiminde de varsayılan marj olarak kullanılır.
                        </div>
                    </div>
                    <div class="form-check mb-3">
                        <input type="checkbox" name="auto_sync_enabled" value="1" class="form-check-input" id="autoSync"
                               @checked(old('auto_sync_enabled', $dealer->auto_sync_enabled))>
                        <label class="form-check-label" for="autoSync">Saatlik otomatik XML senkronuna dahil et</label>
                        <div class="form-text">Kapalıysa genel katalog yine güncellenir; sadece “son aktarım” işareti atılmaz.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small">Entegrasyon API anahtarı</label>
                        <div class="input-group input-group-sm">
                            <input type="text" class="form-control font-monospace" readonly
                                   value="{{ $dealer->integration_api_key ?: 'Henüz yok — aşağıdaki kutuyu işaretleyip kaydedin' }}">
                        </div>
                        <div class="form-check mt-1">
                            <input type="checkbox" name="regenerate_api_key" value="1" class="form-check-input" id="regenApi">
                            <label class="form-check-label small" for="regenApi">Yeni API anahtarı üret</label>
                        </div>
                    </div>
                    <div class="mb-0">
                        <label class="form-label small">XML token</label>
                        <div class="input-group input-group-sm">
                            <input type="text" class="form-control font-monospace" readonly value="{{ $dealer->xml_token }}">
                        </div>
                        <div class="form-check mt-1">
                            <input type="checkbox" name="regenerate_xml_token" value="1" class="form-check-input" id="regenXml">
                            <label class="form-check-label small text-danger" for="regenXml">XML token’ı yenile (eski link çalışmaz)</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card">
                <div class="card-header bg-white fw-semibold">Trendyol entegrasyon bilgileri</div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-md-4">
                            <label class="form-label small">Satıcı ID (Supplier ID)</label>
                            <input name="trendyol_seller_id" class="form-control form-control-sm"
                                   value="{{ old('trendyol_seller_id', $dealer->trendyol_seller_id) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small">API Key</label>
                            <input name="trendyol_api_key" type="password" class="form-control form-control-sm"
                                   placeholder="{{ $dealer->hasTrendyolCredentials() ? '•••••••• (değiştirmek için yazın)' : 'API Key' }}"
                                   autocomplete="off">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small">API Secret</label>
                            <input name="trendyol_api_secret" type="password" class="form-control form-control-sm"
                                   placeholder="{{ $dealer->hasTrendyolCredentials() ? '•••••••• (değiştirmek için yazın)' : 'API Secret' }}"
                                   autocomplete="off">
                        </div>
                    </div>
                    @if($dealer->trendyol_last_error)
                        <div class="alert alert-warning small mt-3 mb-0">Son Trendyol hatası: {{ $dealer->trendyol_last_error }}</div>
                    @endif
                    <p class="small text-muted mt-2 mb-0">
                        Kaydettikten sonra ürün göndermek için
                        <a href="{{ route('admin.dealers.trendyol', $dealer) }}">Trendyol sayfasına</a> gidin.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-3">
        <button class="btn btn-primary">
            <i class="bi bi-check2-circle me-1"></i> Bayi ayarlarını kaydet
        </button>
    </div>
</form>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header bg-white fw-semibold">Son siparişler</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th>#</th><th>Müşteri</th><th>Tutar</th><th>Durum</th><th>Tarih</th></tr></thead>
                    <tbody>
                    @forelse($dealer->orders as $o)
                        <tr>
                            <td><a href="{{ route('admin.orders.show', $o) }}">{{ $o->order_number }}</a></td>
                            <td>{{ $o->customer_name }}</td>
                            <td>{{ number_format($o->total, 2) }} ₺</td>
                            <td>{{ $o->status }}</td>
                            <td>{{ $o->created_at->format('d.m.Y H:i') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-muted text-center py-3">Sipariş yok</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header bg-white fw-semibold">Son bakiye hareketleri</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Tür</th><th>Tutar</th><th>Bakiye</th><th>Tarih</th></tr></thead>
                    <tbody>
                    @forelse($transactions as $t)
                        <tr>
                            <td>{{ $t->type }}</td>
                            <td class="{{ $t->amount >= 0 ? 'text-success' : 'text-danger' }}">
                                {{ number_format($t->amount, 2) }} ₺
                            </td>
                            <td>{{ number_format($t->balance_after, 2) }} ₺</td>
                            <td>{{ $t->created_at->format('d.m H:i') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-muted text-center py-3">Hareket yok</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
