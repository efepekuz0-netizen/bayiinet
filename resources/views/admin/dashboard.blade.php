@extends('layouts.app')
@section('title', 'Panel')
@section('content')
<div class="hero-banner d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
    <div>
        <div class="small opacity-75">YÖNETİM ÖZETİ · {{ now()->translatedFormat('j F Y, l') }}</div>
        <h4 class="mb-1 mt-1">İyi günler, {{ auth()->user()->name }} 👋</h4>
        <div class="small opacity-75">{{ $stats['active_dealers'] }} aktif bayi · {{ $stats['sources'] }} XML kaynağı · Son 14 gün</div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="{{ route('admin.orders.index') }}" class="btn btn-sm btn-outline-light">Siparişleri yönet</a>
        <a href="{{ route('admin.export.xml') }}" class="btn btn-sm btn-light"><i class="bi bi-download me-1"></i>Ürün XML'ini indir</a>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card p-3 h-100">
            <div class="d-flex justify-content-between"><span class="text-muted small">SİPARİŞ · 14 GÜN</span><i class="bi bi-bag-check text-primary"></i></div>
            <div class="fs-3 fw-bold text-primary">{{ number_format($stats['orders']) }}</div>
            <small class="text-muted">{{ number_format($stats['revenue'], 2) }} ₺ net sipariş hacmi</small>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card green p-3 h-100">
            <div class="d-flex justify-content-between"><span class="text-muted small">TAHMİNİ BAYİ MARJI</span><i class="bi bi-percent text-success"></i></div>
            <div class="fs-3 fw-bold text-success">{{ number_format($stats['estimated_margin'], 2) }} ₺</div>
            <small class="text-muted">Katalog varsayılan marjı: %{{ number_format($stats['margin_rate'], 2) }}</small>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card p-3 h-100" style="border-top-color:#8b5cf6">
            <div class="d-flex justify-content-between"><span class="text-muted small">ÜRÜN HAVUZU</span><i class="bi bi-box" style="color:#8b5cf6"></i></div>
            <div class="fs-3 fw-bold">{{ number_format($stats['products']) }}</div>
            <small class="text-muted">{{ number_format($stats['stock_units']) }} toplam stok · {{ number_format($stats['active_products']) }} aktif ürün</small>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card orange p-3 h-100">
            <div class="d-flex justify-content-between"><span class="text-muted small">KRİTİK STOK</span><i class="bi bi-exclamation-triangle text-warning"></i></div>
            <div class="fs-3 fw-bold text-warning">{{ number_format($stats['critical_stock']) }}</div>
            <small class="text-muted">Eşik: {{ $stats['critical_threshold'] }} ve altı</small>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-xl-7">
        <div class="card h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <strong>Sipariş durumu</strong>
                <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.orders.index') }}">Tümünü aç</a>
            </div>
            <div class="card-body">
                <div class="d-flex gap-2 flex-wrap mb-3">
                    @foreach(['pending' => 'Yeni', 'paid' => 'Ödendi', 'preparing' => 'Hazırlanıyor', 'shipped' => 'Kargoda', 'delivered' => 'Teslim edildi', 'returned' => 'İade', 'cancelled' => 'İptal'] as $status => $label)
                        <a href="{{ route('admin.orders.index', ['status' => $status]) }}" class="btn btn-sm btn-light border">{{ $label }} <strong>{{ $statusCounts[$status] ?? 0 }}</strong></a>
                    @endforeach
                </div>
                <div class="d-flex align-items-end gap-2" style="height:145px">
                    @php($maxDailyOrders = max(1, $dailyOrders->max('count')))
                    @foreach($dailyOrders as $daily)
                        <div class="flex-fill text-center h-100 d-flex flex-column justify-content-end" title="{{ $daily['label'] }}: {{ $daily['count'] }} sipariş">
                            <div class="bg-primary rounded-top w-100" style="height:{{ max(3, (int) ($daily['count'] / $maxDailyOrders * 100)) }}%"></div>
                            @if($loop->first || $loop->last || $loop->iteration % 4 === 0)<small class="text-muted mt-1" style="font-size:.65rem">{{ $daily['label'] }}</small>@endif
                        </div>
                    @endforeach
                </div>
                <div class="text-muted small mt-2">Son 14 gün günlük sipariş sayısı</div>
            </div>
        </div>
    </div>
    <div class="col-xl-5">
        <div class="card h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <strong>Bayiler</strong>
                <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.dealers.index') }}">Yönet</a>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>BAYİ</th><th>SİPARİŞ</th><th>BAKİYE</th><th>DURUM</th></tr></thead>
                    <tbody>
                    @forelse($dealers as $dealer)
                        <tr>
                            <td><a href="{{ route('admin.dealers.show', $dealer) }}">{{ $dealer->company_name }}</a><div class="small text-muted">{{ $dealer->user->email ?? '' }}</div></td>
                            <td>{{ $dealer->orders_count }}</td>
                            <td>{{ number_format($dealer->balance, 2) }} ₺</td>
                            <td><span class="badge text-bg-{{ $dealer->status_tone }}">{{ $dealer->status_label }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-4">Henüz bayi kaydı yok.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-xl-6">
        <div class="card h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center"><strong>XML kaynakları</strong><a href="{{ route('admin.sources.index') }}" class="btn btn-sm btn-outline-secondary">Yönet</a></div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>KAYNAK</th><th>ÜRÜN</th><th>STOK</th><th>SON AKTARIM</th></tr></thead>
                    <tbody>
                    @forelse($sources as $source)
                        <tr>
                            <td>{{ $source->name }}</td>
                            <td>{{ number_format($source->products_count) }}</td>
                            <td>{{ number_format($source->products()->sum('stock')) }}</td>
                            <td>{{ $source->last_imported_at?->diffForHumans() ?? 'Henüz yok' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-4">XML kaynağı ekleyerek başlayın.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-xl-6">
        <div class="card h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center"><strong>Son sistem işlemleri</strong><a href="{{ route('admin.logs.index') }}" class="btn btn-sm btn-outline-secondary">Tüm kayıtlar</a></div>
            <ul class="list-group list-group-flush">
                @forelse($recentImports as $import)
                    <li class="list-group-item d-flex justify-content-between gap-3">
                        <div><span class="text-success me-2">●</span><strong>{{ $import->status === 'completed' ? 'XML içe aktarma' : 'XML aktarımı' }}</strong><div class="small text-muted">{{ $import->source->name ?? 'Silinmiş kaynak' }} · {{ $import->created_count }} yeni, {{ $import->updated_count }} güncellendi, {{ $import->error_count }} hata</div></div>
                        <small class="text-muted text-nowrap">{{ $import->created_at->diffForHumans() }}</small>
                    </li>
                @empty
                    <li class="list-group-item text-muted">Henüz işlem kaydı yok.</li>
                @endforelse
            </ul>
        </div>
    </div>
</div>
@endsection
