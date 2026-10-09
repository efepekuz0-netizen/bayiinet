@extends('layouts.app')
@section('title', 'Sistem Sağlığı')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h4 class="mb-1">Sistem sağlığı</h4>
        <div class="text-muted small">Veritabanı şeması, migration durumu, kuyruk ve anasayfa duman testi.</div>
    </div>
    <a href="{{ route('admin.operations.health') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-clockwise me-1"></i>Yenile</a>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card mb-3">
            <div class="card-header bg-white fw-semibold">Anasayfa / ürün testleri</div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th>TEST</th><th>DURUM</th><th>AÇIKLAMA</th></tr></thead>
                    <tbody>
                    @forelse($tests as $test)
                        <tr>
                            <td class="fw-semibold">{{ $test['label'] }}</td>
                            <td>
                                @if($test['ok'])
                                    <span class="badge text-bg-success"><i class="bi bi-check-circle me-1"></i>Tamam</span>
                                @else
                                    <span class="badge text-bg-danger"><i class="bi bi-x-circle me-1"></i>Hata</span>
                                @endif
                            </td>
                            <td class="small text-break">{{ $test['detail'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-center text-muted py-4">Test çalıştırılamadı.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if(!empty($cardErrors))
            <div class="card border-danger mb-3">
                <div class="card-header bg-white fw-semibold text-danger">Kart render hataları (500 sebebi)</div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0 small">
                        <thead><tr><th>ÜRÜN</th><th>HATA</th><th>KONUM</th></tr></thead>
                        <tbody>
                        @foreach($cardErrors as $err)
                            <tr>
                                <td class="text-break">#{{ $err['product_id'] }}<br><span class="text-muted">{{ \Illuminate\Support\Str::limit((string) $err['title'], 50) }}</span></td>
                                <td class="text-break text-danger">{{ $err['error'] }}</td>
                                <td class="text-break text-muted">{{ $err['file'] }}:{{ $err['line'] }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        <div class="card mb-3">
            <div class="card-header bg-white fw-semibold">Şema kontrolleri</div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th>KONTROL</th><th>DURUM</th><th>AÇIKLAMA</th></tr></thead>
                    <tbody>
                    @forelse($checks as $check)
                        <tr>
                            <td class="fw-semibold">{{ $check['label'] }}</td>
                            <td>
                                @if($check['ok'])
                                    <span class="badge text-bg-success">Tamam</span>
                                @else
                                    <span class="badge text-bg-danger">Hata</span>
                                @endif
                            </td>
                            <td class="small text-break">{{ $check['detail'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-center text-muted py-4">Kontrol yapılamadı.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card mb-3">
            <div class="card-header bg-white fw-semibold">Tablolar</div>
            <div class="card-body">
                <div class="d-flex flex-wrap gap-2">
                    @foreach($tableStatus as $table => $exists)
                        <span class="badge {{ $exists ? 'text-bg-light border' : 'text-bg-danger' }}">
                            <i class="bi {{ $exists ? 'bi-check' : 'bi-x' }} me-1"></i>{{ $table }}
                        </span>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header bg-white fw-semibold">Otomasyon</div>
            <div class="card-body small">
                <div class="d-flex justify-content-between border-bottom py-1">
                    <span class="text-muted">XML otomatik senkron</span>
                    <span class="badge {{ ($automation['xml'] ?? false) ? 'text-bg-success' : 'text-bg-secondary' }}">{{ ($automation['xml'] ?? false) ? 'Açık' : 'Kapalı' }}</span>
                </div>
                <div class="d-flex justify-content-between border-bottom py-1">
                    <span class="text-muted">Trendyol otomatik senkron</span>
                    <span class="badge {{ ($automation['trendyol'] ?? false) ? 'text-bg-success' : 'text-bg-secondary' }}">{{ ($automation['trendyol'] ?? false) ? 'Açık' : 'Kapalı' }}</span>
                </div>
                <div class="d-flex justify-content-between border-bottom py-1">
                    <span class="text-muted">Zamanlayıcı</span>
                    <span class="badge {{ ($heartbeat['scheduler_alive'] ?? false) ? 'text-bg-success' : 'text-bg-warning' }}">{{ ($heartbeat['scheduler_alive'] ?? false) ? 'Çalışıyor' : 'Sinyal yok' }}</span>
                </div>
                <div class="d-flex justify-content-between border-bottom py-1">
                    <span class="text-muted">Kuyruk (bekleyen)</span>
                    <span class="fw-semibold">{{ number_format($queue['pending'] ?? 0) }}</span>
                </div>
                <div class="d-flex justify-content-between py-1">
                    <span class="text-muted">Başarısız iş</span>
                    <span class="fw-semibold {{ ($queue['failed'] ?? 0) > 0 ? 'text-danger' : '' }}">{{ number_format($queue['failed'] ?? 0) }}</span>
                </div>
            </div>
        </div>

        @if(!empty($heartbeat['jobs']))
            <div class="card mb-3">
                <div class="card-header bg-white fw-semibold">Son çalışan işler</div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0 small">
                        <thead><tr><th>İŞ</th><th>DURUM</th><th>ZAMAN</th></tr></thead>
                        <tbody>
                        @foreach($heartbeat['jobs'] as $name => $info)
                            <tr>
                                <td class="text-break">{{ $name }}</td>
                                <td>
                                    <span class="badge {{ ($info['status'] ?? '') === 'ok' ? 'text-bg-success' : 'text-bg-danger' }}">{{ $info['status'] ?? '-' }}</span>
                                </td>
                                <td class="text-muted">{{ $info['at'] ?? $info['last_run_at'] ?? '-' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        @if($failedJobs->isNotEmpty())
            <div class="card">
                <div class="card-header bg-white fw-semibold text-danger">Son başarısız işler</div>
                <div class="card-body small">
                    @foreach($failedJobs as $job)
                        <div class="border-bottom pb-2 mb-2">
                            <div class="fw-semibold text-break">{{ $job->queue ?? 'default' }}</div>
                            <div class="text-muted text-break">{{ \Illuminate\Support\Str::limit((string) ($job->exception ?? ''), 220) }}</div>
                            <div class="text-muted">{{ $job->failed_at ?? '' }}</div>
                        </div>
                    @endforeach
                    <div class="text-muted">Düzeltme sonrası <code>php artisan queue:retry all</code> ile yeniden deneyebilirsin.</div>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
