@extends('layouts.app')

@section('title', 'Otomasyon')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h4 class="mb-1">Otomasyon & Zamanlanmış Görevler</h4>
            <div class="text-muted small">XML senkronu, Trendyol gönderimi ve batch kontrollerinin son çalışma zamanı ve sonuçları.</div>
        </div>
        <div class="d-flex gap-2">
            <form method="POST" action="{{ route('admin.automation.xml') }}">
                @csrf
                <button class="btn btn-outline-primary btn-sm"><i class="bi bi-arrow-repeat me-1"></i> Şimdi XML çek</button>
            </form>
            <form method="POST" action="{{ route('admin.automation.trendyol') }}">
                @csrf
                <button class="btn btn-outline-primary btn-sm"><i class="bi bi-send me-1"></i> Şimdi Trendyol senkronu</button>
            </form>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3">
            <div class="card stat-card p-3 h-100">
                <div class="text-muted small">Zamanlayıcı</div>
                <div class="fs-5 fw-semibold {{ $scheduler['alive'] ? 'text-success' : 'text-danger' }}">
                    <i class="bi {{ $scheduler['alive'] ? 'bi-check-circle-fill' : 'bi-x-circle-fill' }} me-1"></i>
                    {{ $scheduler['alive'] ? 'Çalışıyor' : 'Durmuş' }}
                </div>
                <div class="text-muted small mt-1">Son nabız: {{ $scheduler['last_seen'] ?? 'hiç' }}</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card stat-card {{ $queue['pending'] > 200 ? 'orange' : 'green' }} p-3 h-100">
                <div class="text-muted small">Kuyrukta bekleyen iş</div>
                <div class="fs-5 fw-semibold">{{ number_format($queue['pending'], 0, ',', '.') }}</div>
                <div class="text-muted small mt-1">
                    Trendyol kuyruğu: {{ number_format($queue['done'], 0, ',', '.') }} · başarısız: {{ number_format($queue['failed'], 0, ',', '.') }}
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card stat-card p-3 h-100">
                <div class="text-muted small">Otomatik XML senkronu</div>
                <div class="fs-5 fw-semibold">{{ $automation['xml_sync'] ? 'Açık' : 'Kapalı' }}</div>
                <div class="text-muted small mt-1">Saatlik · AUTO_XML_SYNC</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card stat-card p-3 h-100">
                <div class="text-muted small">Otomatik Trendyol senkronu</div>
                <div class="fs-5 fw-semibold">{{ $automation['trendyol_sync'] ? 'Açık' : 'Kapalı' }}</div>
                <div class="text-muted small mt-1">Saatlik · AUTO_TRENDYOL_SYNC</div>
            </div>
        </div>
    </div>

    @unless($scheduler['alive'])
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle-fill me-1"></i>
            Zamanlayıcıdan son 15 dakikada nabız alınmadı. Saatlik görevlerin çalışması için sunucuda
            <code>php artisan schedule:work</code> (veya cron ile <code>schedule:run</code>) sürecinin ayakta olması
            ve <code>php artisan queue:work</code> işçisinin çalışması gerekir.
        </div>
    @endunless

    <div class="card">
        <div class="card-header bg-white fw-semibold">Zamanlanmış görevler</div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Görev</th>
                        <th>Sıklık</th>
                        <th>Son çalışma</th>
                        <th>Durum</th>
                        <th>Sonuç</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($jobs as $job)
                        <tr>
                            <td class="fw-semibold">
                                <i class="bi {{ $job['icon'] }} me-1 text-secondary"></i>{{ $job['title'] }}
                            </td>
                            <td class="text-muted small">{{ $job['schedule'] }}</td>
                            <td class="small">
                                @if($job['ran_at'])
                                    {{ $job['ran_at'] }}
                                    @if($job['minutes_ago'] !== null)
                                        <span class="text-muted">({{ $job['minutes_ago'] }} dk önce)</span>
                                    @endif
                                @else
                                    <span class="text-muted">Henüz çalışmadı</span>
                                @endif
                            </td>
                            <td>
                                @php
                                    $badge = match ($job['status']) {
                                        'ok' => 'success',
                                        'warning' => 'warning',
                                        'error' => 'danger',
                                        'running' => 'primary',
                                        default => 'secondary',
                                    };
                                    $label = match ($job['status']) {
                                        'ok' => 'Başarılı',
                                        'warning' => 'Uyarı',
                                        'error' => 'Hata',
                                        'running' => 'Sürüyor',
                                        default => 'Bilinmiyor',
                                    };
                                @endphp
                                <span class="badge text-bg-{{ $badge }}">{{ $label }}</span>
                            </td>
                            <td class="small text-muted">{{ $job['message'] ?: '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white small text-muted">
            <i class="bi bi-info-circle me-1"></i>
            Görevler <code>routes/console.php</code> içinde tanımlıdır. Kuyruk sürücüsü <strong>{{ config('queue.default') }}</strong>.
            Trendyol gönderimi <code>marketplace</code>, XML içe aktarma <code>default</code> kuyruğunda çalışır.
        </div>
    </div>
@endsection
