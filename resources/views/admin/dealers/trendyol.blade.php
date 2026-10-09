@extends('layouts.app')
@section('title', $dealer->company_name.' · Trendyol')
@section('content')
@if(!empty($sendStatus) && ($sendStatus['status'] ?? 'idle') !== 'idle')
    @php
        $st = $sendStatus['status'] ?? 'idle';
        $total = (int) ($sendStatus['total'] ?? 0);
        $processed = (int) ($sendStatus['processed'] ?? 0);
        $sent = (int) ($sendStatus['sent'] ?? 0);
        $pending = (int) ($sendStatus['pending'] ?? 0);
        $failed = (int) ($sendStatus['failed'] ?? 0);
        $batchesDone = (int) ($sendStatus['batches_done'] ?? 0);
        $totalBatches = (int) ($sendStatus['total_batches'] ?? 0);
        $percent = $total > 0 ? min(100, (int) round($processed * 100 / $total)) : 0;
        $tone = match ($st) {
            'error' => 'danger',
            'cancelled' => 'secondary',
            'done' => ($failed > 0 ? 'warning' : 'success'),
            default => 'info',
        };
    @endphp
    <div class="card border-{{ $tone }} mb-3" id="sendStatusCard">
        <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span class="fw-semibold"><i class="bi bi-send me-1"></i> Trendyol gönderim durumu</span>
            <span class="badge text-bg-{{ $tone }}">{{ $st }}</span>
        </div>
        <div class="card-body">
            <div class="d-flex justify-content-between small text-muted mb-1">
                <span>{{ $processed }} / {{ $total }} ürün işlendi</span>
                <span>{{ $batchesDone }} / {{ $totalBatches }} parça</span>
            </div>
            <div class="progress mb-3" style="height:8px" role="progressbar" aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100">
                <div class="progress-bar {{ in_array($st, ['running', 'queued'], true) ? 'progress-bar-striped progress-bar-animated' : '' }} bg-{{ $tone }}"
                     style="width: {{ $percent }}%"></div>
            </div>
            <div class="d-flex flex-wrap gap-3 mb-2">
                <span class="badge text-bg-success">Gönderilen: {{ $sent }}</span>
                <span class="badge text-bg-info">Doğrulama bekleyen: {{ $pending }}</span>
                <span class="badge text-bg-danger">Hatalı: {{ $failed }}</span>
            </div>
            <div class="small">{{ $sendStatus['message'] ?? '' }}</div>

            @if(!empty($sendStatus['errors']) && is_array($sendStatus['errors']))
                <div class="mt-2">
                    <button class="btn btn-link btn-sm p-0" type="button" data-bs-toggle="collapse" data-bs-target="#sendErrors">
                        Son hatalar ({{ count($sendStatus['errors']) }})
                    </button>
                    <div class="collapse mt-1" id="sendErrors">
                        <ul class="small mb-0 ps-3">
                            @foreach(array_slice($sendStatus['errors'], 0, 10) as $err)
                                <li>{{ $err }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif

            @if(in_array($st, ['running', 'queued'], true))
                <div class="d-flex flex-wrap align-items-center gap-2 mt-3">
                    <span class="small text-muted"><i class="bi bi-arrow-repeat me-1"></i>Sayfa 15 saniyede bir yenilenir; işlem arka planda sürer.</span>
                    <form method="POST" action="{{ route('admin.dealers.trendyol.cancel', $dealer) }}" class="ms-auto"
                          onsubmit="return confirm('Gönderim dursun mu? Kuyrukta bekleyen parçalar atlanır, gönderilenler Trendyol\'da kalır.')">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline-danger">Gönderimi durdur</button>
                    </form>
                </div>
                <script>
                    (function () {
                        const KEY = 'trendyolScrollY';
                        const saved = sessionStorage.getItem(KEY);
                        if (saved) {
                            window.scrollTo(0, parseInt(saved, 10));
                            sessionStorage.removeItem(KEY);
                        }
                        window.addEventListener('beforeunload', function () {
                            sessionStorage.setItem(KEY, String(window.scrollY || 0));
                        });
                        setTimeout(function () { location.reload(); }, 15000);
                    })();
                </script>
            @else
                <div class="d-flex flex-wrap align-items-center gap-2 mt-3">
                    <form method="POST" action="{{ route('admin.dealers.trendyol.recheck', $dealer) }}">
                        @csrf
                        <button class="btn btn-sm btn-outline-primary">Trendyol sonucunu şimdi çek</button>
                    </form>
                    <span class="small text-muted">«Doğrulama bekleyen» sayısı 15 dakikada bir otomatik olarak da güncellenir.</span>
                </div>
            @endif

            @if($st === 'cancelled')
                <div class="small text-muted mt-2">Durduruldu. İsterseniz yeniden «Tüm ürünleri gönder» deyin.</div>
            @endif
        </div>
    </div>
@endif

@if(!empty($deleteStatus))
    <div class="alert alert-{{ ($deleteStatus['status'] ?? '') === 'error' ? 'danger' : (($deleteStatus['status'] ?? '') === 'done' ? 'success' : 'warning') }} mb-3">
        <strong>Trendyol silme:</strong> {{ $deleteStatus['message'] ?? '' }}
        @if(!empty($deleteStatus['deleted'])) · {{ $deleteStatus['deleted'] }} silindi @endif
    </div>
@endif

<div class="card border-danger mb-4">
    <div class="card-header bg-white text-danger fw-semibold">Trendyol ürün sil</div>
    <div class="card-body">
        <p class="small text-muted mb-3">Bayinin Trendyol mağazasından ürün kaldırır. Yerel katalog ürünleri silinmez; sadece Trendyol listing + mağaza ürünü.</p>
        <form method="POST" action="{{ route('admin.dealers.trendyol.delete', $dealer) }}" class="row g-2 align-items-end"
              onsubmit="return confirm('Seçilen kapsam Trendyol\'dan silinecek. Emin misiniz?')">
            @csrf
            <div class="col-md-6">
                <label class="form-label small">Kapsam</label>
                <select name="scope" class="form-select" required>
                    <option value="all">Tüm Trendyol ürünleri (bu bayi)</option>
                    @foreach($sources ?? [] as $src)
                        <option value="source:{{ $src->id }}">Sadece XML: {{ $src->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <div class="form-check mt-4">
                    <input class="form-check-input" type="checkbox" name="confirm" value="1" id="delConfirm" required>
                    <label class="form-check-label small" for="delConfirm">Silmeyi onaylıyorum</label>
                </div>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-outline-danger w-100">Sil</button>
            </div>
        </form>
    </div>
</div>

@php
    $statusLabels = ['pending' => 'Hazırlandı', 'sent' => 'Doğrulama bekliyor', 'created' => 'Trendyol\'da oluştu', 'failed' => 'Hatalı'];
    $statusColors = ['pending' => 'secondary', 'sent' => 'info', 'created' => 'success', 'failed' => 'danger'];
@endphp

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-0">{{ $dealer->company_name }} <span class="text-muted fw-normal">/ Trendyol</span></h4>
        <a href="{{ route('admin.dealers.show', $dealer) }}" class="small text-decoration-none">&larr; Bayi sayfasına dön</a>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        @foreach(['pending', 'sent', 'created', 'failed'] as $s)
            <span class="badge text-bg-{{ $statusColors[$s] }}">{{ $statusLabels[$s] }}: {{ $counts[$s] ?? 0 }}</span>
        @endforeach
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header bg-white fw-semibold">1. Bayinin Trendyol bağlantısı</div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.dealers.trendyol.connection', $dealer) }}">
                    @csrf @method('PUT')
                    <div class="mb-2">
                        <label class="form-label small mb-1">Satıcı (Mağaza) Numarası</label>
                        <input type="text" name="trendyol_seller_id" class="form-control form-control-sm" value="{{ old('trendyol_seller_id', $dealer->trendyol_seller_id) }}" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small mb-1">API Key</label>
                        <input type="text" name="api_key" class="form-control form-control-sm" autocomplete="off"
                               placeholder="{{ $dealer->hasTrendyolCredentials() ? 'Kayıtlı (değiştirmek için yazın)' : '' }}">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small mb-1">API Secret</label>
                        <input type="password" name="api_secret" class="form-control form-control-sm" autocomplete="new-password"
                               placeholder="{{ $dealer->hasTrendyolCredentials() ? 'Kayıtlı (değiştirmek için yazın)' : '' }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small mb-1">Bayi kâr yüzdesi (%)</label>
                        <input type="number" step="0.01" min="0" max="500" name="default_marketplace_margin" class="form-control form-control-sm"
                               value="{{ old('default_marketplace_margin', $dealer->default_marketplace_margin ?? 20) }}" required>
                        <div class="form-text">Trendyol satış fiyatı = bayiye satış fiyatınız + bu yüzde.</div>
                    </div>
                    <button class="btn btn-primary btn-sm">Kaydet ve bağlantıyı dene</button>
                </form>
                @if($dealer->hasTrendyolCredentials())
                    <form method="POST" action="{{ route('admin.dealers.trendyol.test', $dealer) }}" class="mt-2">@csrf
                        <button class="btn btn-outline-secondary btn-sm">Sadece bağlantıyı dene</button>
                    </form>
                @endif
                @if($dealer->trendyol_last_error)
                    <div class="alert alert-warning small mt-3 mb-0">Son hata: {{ $dealer->trendyol_last_error }}</div>
                @endif
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header bg-white fw-semibold">3. Sonuç ve eşitleme</div>
            <div class="card-body">
                <p class="small text-muted mb-2">Trendyol ürünleri hemen değil, birkaç dakika içinde işler. Gönderimden sonra sonucu buradan sorgulayın.</p>
                <form method="POST" action="{{ route('admin.dealers.trendyol.batch', $dealer) }}" class="d-flex gap-2 mb-3">
                    @csrf
                    <select name="batch_request_id" class="form-select form-select-sm" required>
                        <option value="">Gönderim seçin…</option>
                        @foreach($batches as $b)
                            <option value="{{ $b }}">{{ $b }}</option>
                        @endforeach
                    </select>
                    <button class="btn btn-outline-primary btn-sm text-nowrap">Sonuç sorgula</button>
                </form>
                <form method="POST" action="{{ route('admin.dealers.trendyol.inventory', $dealer) }}"
                      onsubmit="return confirm('Gönderilmiş tüm ürünlerin fiyat ve stoğu Trendyol\'da güncellensin mi?')">
                    @csrf
                    <button class="btn btn-outline-success btn-sm" @disabled(! $dealer->hasTrendyolCredentials())>Fiyat ve stokları şimdi eşitle</button>
                </form>
                <form method="POST" action="{{ route('admin.dealers.trendyol.recheck', $dealer) }}" class="mt-2">
                    @csrf
                    <button class="btn btn-warning btn-sm w-100">Gönderildi olanları kontrol et (Trendyol sonucu)</button>
                    <div class="form-text">«Gönderildi» ama «Trendyol'da oluştu» 0 ise buna bas — gerçek kabul/red durumunu çeker.</div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header bg-white fw-semibold">2. Ürün seç ve gönder</div>
    <div class="card-body">
        <form method="GET" class="d-flex gap-2 mb-3">
            <input type="text" name="q" value="{{ $search }}" class="form-control form-control-sm" placeholder="Ürün adı, stok kodu veya barkod ara">
            <button class="btn btn-outline-secondary btn-sm">Ara</button>
        </form>

        <form method="POST" action="{{ route('admin.dealers.trendyol.send', $dealer) }}">
            @csrf
            <div class="row g-2 mb-3">
                <div class="col-md-3">
                    <label class="form-label small mb-1">Trendyol Kategori No</label>
                    <input type="number" name="category_id" class="form-control form-control-sm" value="{{ old('category_id', $defaultCategoryId ?? '') }}" placeholder="Boş = ürüne göre otomatik">
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-1">Trendyol Marka No</label>
                    <input type="number" name="brand_id" class="form-control form-control-sm" value="{{ old('brand_id', $defaultBrandId ?? '') }}" placeholder="Boş = ürün markasından otomatik">
                </div>
                <div class="col-md-6">
                    <label class="form-label small mb-1">Kategori özellikleri (JSON, isteğe bağlı)</label>
                    <input type="text" name="attributes_json" class="form-control form-control-sm" value="{{ old('attributes_json') }}"
                           placeholder='[{"attributeId":338,"attributeValueId":6980}]'>
                </div>
            </div>
            <div class="form-text mb-3">
                <strong>Kategori ve marka otomatik seçilir</strong> (ürün başlığı + XML kategorisi + anahtar kelimeler; masaüstü mantığı).
                İsterseniz alanları doldurarak zorla override edebilirsiniz; bir kez girilen marka varsayılan yedek olarak saklanır.
                <strong>Tüm ürünleri gönder</strong> stoklu aktif ürünlerin tamamını yollar. Zorunlu özellik eksikse “Sonuç sorgula” ile hata görünür.
            </div>

            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead>
                        <tr>
                            <th style="width:32px"><input type="checkbox" class="form-check-input" onclick="document.querySelectorAll('.pick').forEach(c => c.checked = this.checked)"></th>
                            <th>Ürün</th>
                            <th>Stok kodu</th>
                            <th class="text-end">Stok</th>
                            <th class="text-end">Trendyol fiyatı (motor)</th>
                            <th>Durum</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($products as $p)
                        @php($st = $listed[$p->id] ?? null)
                        <tr>
                            <td><input type="checkbox" class="form-check-input pick" name="product_ids[]" value="{{ $p->id }}"></td>
                            <td>{{ $p->title }}</td>
                            <td><code>{{ $p->stock_code }}</code></td>
                            <td class="text-end">{{ $p->stock }}</td>
                            <td class="text-end">{{ number_format($prices[$p->id], 2, ',', '.') }} ₺</td>
                            <td>
                                @if($st)
                                    <span class="badge text-bg-{{ $statusColors[$st] ?? 'secondary' }}">{{ $statusLabels[$st] ?? $st }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">Ürün bulunamadı. Önce "XML Aktarım Motoru"ndan ürünleri çekin.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-2">
                <div>{{ $products->links() }}</div>
                <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="document.querySelectorAll('.pick').forEach(c=>c.checked=true)">Bu sayfadakileri seç</button>
                    <button type="submit" class="btn btn-primary" @disabled(! $dealer->hasTrendyolCredentials()) name="send_all" value="0">Seçilenleri gönder</button>
                    <button type="submit" class="btn btn-success"
                            @disabled(! $dealer->hasTrendyolCredentials())
                            name="send_all" value="1"
                            onclick="return confirm('Aktif ve stoklu TÜM ürünler bu kategori/marka ile Trendyol\'a gönderilecek. Gönderim arka planda sürer. Devam?')">
                        Tüm ürünleri gönder
                    </button>
                </div>
            </div>
            <div class="form-text mt-2">
                Fiyat = masaüstü motoru: (bayi maliyeti + kargo + kar) ÷ (1 − %15 komisyon), xx.99 yuvarlama.
                Kategori/marka bir kez girilince varsayılan olarak kaydedilir.
            </div>
            <input type="hidden" name="remember_category" value="1">
        </form>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header bg-white fw-semibold">Son gönderilen ürünler</div>
    <div class="table-responsive">
        <table class="table table-sm mb-0 align-middle">
            <thead><tr><th>Ürün</th><th>Barkod</th><th class="text-end">Fiyat</th><th class="text-end">Stok</th><th>Durum</th><th>Not</th></tr></thead>
            <tbody>
            @forelse($recent as $l)
                <tr>
                    <td>{{ $l->product->title ?? '-' }}</td>
                    <td><code>{{ $l->barcode }}</code></td>
                    <td class="text-end">{{ number_format($l->sale_price, 2, ',', '.') }} ₺</td>
                    <td class="text-end">{{ $l->quantity }}</td>
                    <td><span class="badge text-bg-{{ $statusColors[$l->status] ?? 'secondary' }}">{{ $statusLabels[$l->status] ?? $l->status }}</span></td>
                    <td class="small text-muted" style="max-width:360px">{{ $l->error }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted py-3">Henüz ürün gönderilmedi.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
