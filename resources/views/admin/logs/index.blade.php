@extends('layouts.app')
@section('title', 'Kayıtlar')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div><h4 class="mb-1">Sistem kayıtları</h4><div class="text-muted small">XML aktarım geçmişi ve bayi bakiye hareketleri.</div></div>
    <a href="{{ route('admin.marketplace.index') }}" class="btn btn-outline-primary btn-sm">Pazaryeri kontrolü</a>
</div>
<div class="row g-3 mb-3">
    <div class="col-md-4"><div class="card stat-card p-3"><div class="small text-muted">SON XML AKTARIMLARI</div><div class="fs-3 fw-bold">{{ $imports->total() }}</div></div></div>
    <div class="col-md-4"><div class="card stat-card green p-3"><div class="small text-muted">SON BAKİYE HAREKETLERİ</div><div class="fs-3 fw-bold">{{ $transactions->count() }}</div></div></div>
    <div class="col-md-4"><div class="card stat-card orange p-3"><div class="small text-muted">KUYRUK DURUMU</div><div class="fs-5 fw-bold">Eşzamanlı aktarım</div><div class="small text-muted">XML şu an panel isteği sırasında işleniyor.</div></div></div>
</div>
<div class="card mb-3">
    <div class="card-header bg-white fw-semibold">Laravel Log Dosyası (Son 50 satır)</div>
    <div class="card-body">
        <pre class="bg-dark text-light p-3 rounded" style="max-height: 400px; overflow-y: auto; font-size: 12px;">@php
    $logFile = storage_path('logs/laravel.log');
    if (file_exists($logFile)) {
        $lines = array_slice(file($logFile), -50);
        echo implode('', $lines);
    } else {
        echo 'Log dosyası bulunamadı.';
    }
@endphp</pre>
    </div>
</div>
<div class="card mb-3">
    <div class="card-header bg-white fw-semibold">XML aktarım geçmişi</div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>İŞLEM</th><th>KAYNAK / DOSYA</th><th>DURUM</th><th>OKUNAN</th><th>YENİ / GÜNCEL</th><th>HATA</th><th>BAŞLANGIÇ</th><th>SÜRE</th></tr></thead>
            <tbody>
            @forelse($imports as $import)
                <tr>
                    <td>#{{ $import->id }}<div class="small text-muted">{{ $import->user->name ?? 'Sistem' }}</div></td>
                    <td>{{ $import->source->name ?? 'Silinmiş kaynak' }}<div class="small text-muted">{{ $import->file_name }}</div></td>
                    <td><span class="badge text-bg-{{ $import->status_tone }}">{{ $import->status_label }}</span></td>
                    <td>{{ $import->total_products }}</td>
                    <td>{{ $import->created_count }} / {{ $import->updated_count }}</td>
                    <td>{{ $import->error_count }}</td>
                    <td>{{ $import->started_at?->format('d.m.Y H:i') ?? '—' }}</td>
                    <td>{{ $import->finished_at && $import->started_at ? $import->started_at->diffInSeconds($import->finished_at).' sn' : '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-muted py-4">Henüz XML aktarım kaydı yok.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white">{{ $imports->links() }}</div>
</div>
<div class="card">
    <div class="card-header bg-white fw-semibold">Bakiye hareketleri</div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead><tr><th>TARİH</th><th>BAYİ</th><th>İŞLEM</th><th>TUTAR</th><th>SON BAKİYE</th><th>AÇIKLAMA</th></tr></thead>
            <tbody>
            @forelse($transactions as $transaction)
                <tr><td>{{ $transaction->created_at->format('d.m.Y H:i') }}</td><td>{{ $transaction->dealer->company_name ?? 'Silinmiş bayi' }}</td><td>{{ $transaction->type }}</td><td>{{ number_format($transaction->amount, 2) }} ₺</td><td>{{ number_format($transaction->balance_after, 2) }} ₺</td><td>{{ $transaction->description }}</td></tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted py-4">Henüz bakiye hareketi yok.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
