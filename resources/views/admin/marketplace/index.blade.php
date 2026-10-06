@extends('layouts.app')

@section('title', 'Pazaryeri Yönetimi')

@section('content')
    @php($options = $connection?->options ?? [])

    <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-4">
        <div>
            <h4 class="mb-1">Pazaryeri Yönetimi</h4>
            <p class="text-muted mb-0">Mevcut ilanları güncelleyin, pazaryeri siparişlerini ve kargo takibini yönetin.</p>
        </div>
        @if($connection?->last_connected_at)
            <span class="badge text-bg-success px-3 py-2"><i class="bi bi-check-circle me-1"></i>Trendyol bağlı</span>
        @else
            <span class="badge text-bg-secondary px-3 py-2">Trendyol bağlantısı doğrulanmadı</span>
        @endif
    </div>

    <div class="alert alert-info">
        <strong>Güvenli eşitleme:</strong> sadece Trendyol'da zaten onaylı ve eşleşen ilanların fiyatı/stoku güncellenir.
        Eşleşmeyen ürünler atlanır; yeni ilan oluşturulmaz. API anahtarı ve sırrı uygulama anahtarıyla şifrelenerek saklanır.
    </div>

    @if($connection?->last_error)
        <div class="alert alert-warning"><strong>Son bağlantı hatası:</strong> {{ $connection->last_error }}</div>
    @endif

    <div class="row g-3 mb-4">
        @foreach($marketplaces as $marketplace)
            <div class="col-6 col-lg">
                <div class="card h-100 p-3 {{ $marketplace['available'] ? 'border-primary' : '' }}">
                    <div class="d-flex align-items-center justify-content-between gap-2">
                        <strong>{{ $marketplace['name'] }}</strong>
                        @if($marketplace['connected'])
                            <i class="bi bi-check-circle-fill text-success" aria-label="Bağlı"></i>
                        @elseif($marketplace['available'])
                            <i class="bi bi-exclamation-circle text-warning" aria-label="Bağlantı bekliyor"></i>
                        @else
                            <i class="bi bi-lock text-muted" aria-label="API bilgisi gerekli"></i>
                        @endif
                    </div>
                    <small class="text-muted mt-2">
                        {{ $marketplace['connected'] ? 'Bağlantı hazır' : ($marketplace['available'] ? 'API bağlantısı kurulabilir' : 'API bilgileri eklenmedi') }}
                    </small>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row g-3 mb-4">
        <div class="col-xl-7">
            <div class="card h-100">
                <div class="card-header bg-white fw-semibold">Trendyol bağlantı ayarları</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.marketplace.trendyol.update') }}">
                        @csrf
                        @method('PUT')
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label" for="account_id">Mağaza / satıcı numarası</label>
                                <input class="form-control" id="account_id" name="account_id" inputmode="numeric" value="{{ old('account_id', $connection?->account_id) }}" required>
                                @error('account_id')<div class="text-danger small">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="api_key">API anahtarı</label>
                                <input class="form-control" id="api_key" name="api_key" type="password" autocomplete="new-password" placeholder="Değiştirmeyecekseniz boş bırakın">
                                @error('api_key')<div class="text-danger small">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="api_secret">API sırrı</label>
                                <input class="form-control" id="api_secret" name="api_secret" type="password" autocomplete="new-password" placeholder="Değiştirmeyecekseniz boş bırakın">
                                @error('api_secret')<div class="text-danger small">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="barcode_prefix">Stok kodu / barkod öneki</label>
                                <input class="form-control" id="barcode_prefix" name="barcode_prefix" value="{{ old('barcode_prefix', $options['barcode_prefix'] ?? 'SS13-') }}" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="profit_margin">Hedef kâr (%)</label>
                                <input class="form-control" id="profit_margin" name="profit_margin" type="number" min="0" max="100" step="0.01" value="{{ old('profit_margin', number_format((float) ($options['profit_margin'] ?? 0.25) * 100, 2, '.', '')) }}" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="commission_rate">Trendyol komisyonu (%)</label>
                                <input class="form-control" id="commission_rate" name="commission_rate" type="number" min="0" max="99.99" step="0.01" value="{{ old('commission_rate', number_format((float) ($options['commission_rate'] ?? 0.15) * 100, 2, '.', '')) }}" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="min_profit">Asgari net kâr (₺)</label>
                                <input class="form-control" id="min_profit" name="min_profit" type="number" min="0" step="0.01" value="{{ old('min_profit', $options['min_profit'] ?? 10) }}" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="platform_fee">Ek hizmet bedeli (₺)</label>
                                <input class="form-control" id="platform_fee" name="platform_fee" type="number" min="0" step="0.01" value="{{ old('platform_fee', $options['platform_fee'] ?? 0) }}" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="default_desi">Varsayılan desi</label>
                                <input class="form-control" id="default_desi" name="default_desi" type="number" min="0.1" step="0.1" value="{{ old('default_desi', $options['default_desi'] ?? 5) }}" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="round_to">Fiyat yuvarlama</label>
                                <input class="form-control" id="round_to" name="round_to" type="number" min="0.01" max="0.99" step="0.01" value="{{ old('round_to', $options['round_to'] ?? 0.99) }}" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="delivery_type">Kargo tarifesi</label>
                                <select class="form-select" id="delivery_type" name="delivery_type">
                                    <option value="standart" @selected(old('delivery_type', $options['delivery_type'] ?? 'standart') === 'standart')>Standart</option>
                                    <option value="hizli" @selected(old('delivery_type', $options['delivery_type'] ?? 'standart') === 'hizli')>Hızlı</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="min_price">En düşük satış fiyatı (₺)</label>
                                <input class="form-control" id="min_price" name="min_price" type="number" min="0" step="0.01" value="{{ old('min_price', $options['min_price'] ?? '') }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="max_price">En yüksek satış fiyatı (₺)</label>
                                <input class="form-control" id="max_price" name="max_price" type="number" min="0" step="0.01" value="{{ old('max_price', $options['max_price'] ?? '') }}">
                            </div>
                        </div>
                        <div class="d-flex flex-wrap align-items-center gap-2 mt-3">
                            <button class="btn btn-primary" type="submit"><i class="bi bi-shield-lock me-1"></i>Ayarları şifreli kaydet</button>
                            @if($connection)
                                <button class="btn btn-outline-primary" type="submit" form="trendyol-test-form"><i class="bi bi-plug me-1"></i>Bağlantıyı test et</button>
                            @endif
                            @if($connection?->last_connected_at)
                                <small class="text-muted">Son test: {{ $connection->last_connected_at->diffForHumans() }}</small>
                            @endif
                        </div>
                    </form>
                    @if($connection)
                        <form id="trendyol-test-form" method="POST" action="{{ route('admin.marketplace.trendyol.test') }}">@csrf</form>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-xl-5">
            <div class="card h-100">
                <div class="card-header bg-white fw-semibold">Mevcut ilanları güncelle</div>
                <div class="card-body">
                    <p class="text-muted">XML kaynağındaki ürünleri Trendyol'un onaylı ilanlarıyla barkod/stok kodu üzerinden eşleştirir. Fiyat; maliyet, komisyon, kargo ve belirlenen kâr hedefiyle hesaplanır.</p>
                    <form method="POST" action="{{ route('admin.marketplace.trendyol.products.sync') }}">
                        @csrf
                        <label class="form-label" for="source_id">XML ürün kaynağı</label>
                        <select class="form-select mb-3" id="source_id" name="source_id" required>
                            @foreach($sources as $source)
                                <option value="{{ $source->id }}" @selected((string) old('source_id', request('source_id', $sources->first()?->id)) === (string) $source->id)>
                                    {{ $source->name }} · {{ number_format($source->products_count) }} ürün
                                </option>
                            @endforeach
                        </select>
                        @error('source_id')<div class="text-danger small mb-2">{{ $message }}</div>@enderror
                        <button class="btn btn-success" type="submit" @disabled(! $connection?->last_connected_at || $sources->isEmpty() || in_array($recentRun?->status, ['queued', 'processing'], true))>
                            <i class="bi bi-arrow-repeat me-1"></i>Mevcut ilanları eşitle
                        </button>
                    </form>
                    <div class="small text-muted mt-3">Geniş katalog işlemi arka plan kuyruğunda çalışır. Gerekirse uygulama sunucusunda <code>php artisan queue:work marketplace --queue=marketplace --timeout=3600 --tries=1</code> çalıştırın.</div>
                    @if($recentRun)
                        <hr>
                        <div class="d-flex flex-wrap justify-content-between gap-2">
                            <strong>Son işlem: {{ $recentRun->status }}</strong>
                            <span class="text-muted small">{{ $recentRun->created_at->diffForHumans() }}</span>
                        </div>
                        <div class="small text-muted">{{ $recentRun->message }}</div>
                        <div class="d-flex flex-wrap gap-3 small mt-2">
                            <span>İncelenen: {{ number_format($recentRun->total_count) }}</span>
                            <span>Güncellenecek: {{ number_format($recentRun->matched_count) }}</span>
                            <span>Atlanan: {{ number_format($recentRun->skipped_count) }}</span>
                        </div>
                        @foreach($recentRun->batch_request_ids ?? [] as $batchRequestId)
                            <form class="mt-2" method="POST" action="{{ route('admin.marketplace.trendyol.batch', $recentRun) }}">
                                @csrf
                                <input type="hidden" name="batch_request_id" value="{{ $batchRequestId }}">
                                <button class="btn btn-sm btn-outline-secondary" type="submit">Batch sonucunu kontrol et · {{ $batchRequestId }}</button>
                            </form>
                        @endforeach
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <strong>Trendyol siparişleri ve kargo takibi</strong>
                <div class="small text-muted">Son 14 günlük siparişler; her çağrıda en fazla 200 paket alınır.</div>
            </div>
            <form method="POST" action="{{ route('admin.marketplace.trendyol.orders.sync') }}">
                @csrf
                <button class="btn btn-primary btn-sm" type="submit" @disabled(! $connection?->last_connected_at)>
                    <i class="bi bi-cloud-download me-1"></i>{{ $connection?->orders_has_more ? 'Sonraki sipariş sayfasını al' : 'Siparişleri yenile' }}
                </button>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>SİPARİŞ / PAKET</th>
                        <th>TARİH</th>
                        <th>ÜRÜN</th>
                        <th>TUTAR</th>
                        <th>DURUM</th>
                        <th>KARGO TAKİP</th>
                        <th>İŞLEM</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($orders as $order)
                        <tr>
                            <td>
                                <strong>{{ $order->order_number ?: '—' }}</strong>
                                <div class="small text-muted">Paket {{ $order->remote_package_id }}</div>
                            </td>
                            <td>{{ $order->ordered_at?->format('d.m.Y H:i') ?? '—' }}</td>
                            <td>{{ number_format($order->item_count) }}</td>
                            <td>{{ $order->total_amount !== null ? number_format((float) $order->total_amount, 2, ',', '.') . ' ₺' : '—' }}</td>
                            <td><span class="badge text-bg-light border">{{ $order->status }}</span></td>
                            <td>
                                @if($order->cargo_tracking_number)
                                    <div>{{ $order->cargo_company ?: 'Kargo' }}</div>
                                    <strong>{{ $order->cargo_tracking_number }}</strong>
                                    @if($order->cargo_tracking_link)
                                        <a class="d-block small" href="{{ $order->cargo_tracking_link }}" target="_blank" rel="noopener noreferrer">Takip et</a>
                                    @endif
                                @else
                                    <span class="text-muted">Henüz yok</span>
                                @endif
                            </td>
                            <td>
                                @if(mb_strtolower($order->status) === 'created')
                                    <form method="POST" action="{{ route('admin.marketplace.trendyol.orders.status', $order) }}">
                                        @csrf
                                        <input type="hidden" name="status" value="Picking">
                                        <button class="btn btn-sm btn-outline-primary" type="submit">Picking</button>
                                    </form>
                                @elseif(mb_strtolower($order->status) === 'picking')
                                    <form method="POST" action="{{ route('admin.marketplace.trendyol.orders.status', $order) }}" class="d-flex gap-1">
                                        @csrf
                                        <input type="hidden" name="status" value="Invoiced">
                                        <input class="form-control form-control-sm" name="invoice_number" placeholder="Fatura no" aria-label="Fatura numarası" required>
                                        <button class="btn btn-sm btn-success" type="submit">Faturala</button>
                                    </form>
                                @else
                                    <span class="text-muted small">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">Henüz pazaryeri siparişi alınmadı.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($orders->hasPages())
            <div class="card-footer bg-white">{{ $orders->links() }}</div>
        @endif
    </div>

    <div class="card">
        <div class="card-header bg-white fw-semibold">XML tedarik kaynakları</div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>KAYNAK</th><th>ÜRÜN</th><th>SON AKTARIM</th><th>DURUM</th></tr></thead>
                <tbody>
                    @forelse($sources as $source)
                        <tr>
                            <td>{{ $source->name }}</td>
                            <td>{{ number_format($source->products_count) }}</td>
                            <td>{{ $source->last_imported_at?->diffForHumans() ?? '—' }}</td>
                            <td><span class="badge text-bg-{{ $source->is_active ? 'success' : 'secondary' }}">{{ $source->is_active ? 'Aktif' : 'Pasif' }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-4">Henüz XML tedarik kaynağı yok.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
