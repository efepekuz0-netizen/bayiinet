@extends('layouts.app')
@section('title', 'Bayi Panel')
@section('content')
<h4 class="mb-4">Hoş geldiniz, {{ $dealer->company_name }}</h4>

@if($dealer->status === 'pending')
    <div class="alert alert-warning">Bayilik başvurunuz onay bekliyor. Onaylandıktan sonra sipariş verebilirsiniz.</div>
@endif

@if($announcements->isNotEmpty())
    @foreach($announcements as $announcement)
        <div class="alert alert-info"><strong>{{ $announcement->title }}</strong><div>{{ $announcement->body }}</div></div>
    @endforeach
@endif

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card stat-card green p-3">
            <div class="text-muted small">Bakiye</div>
            <div class="fs-3 fw-bold">{{ number_format($stats['balance'], 2) }} ₺</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card p-3">
            <div class="text-muted small">Toplam Sipariş</div>
            <div class="fs-3 fw-bold">{{ $stats['orders'] }}</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card orange p-3">
            <div class="text-muted small">Bekleyen</div>
            <div class="fs-3 fw-bold">{{ $stats['pending_orders'] }}</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card p-3">
            <div class="text-muted small">Ürün Çeşidi</div>
            <div class="fs-3 fw-bold">{{ $stats['products'] }}</div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header bg-white fw-semibold d-flex justify-content-between">
        <span>XML Feed Linkiniz</span>
        <button class="btn btn-sm btn-outline-secondary" onclick="navigator.clipboard.writeText('{{ url('/xml/'.$dealer->xml_token.'.xml') }}')">Kopyala</button>
    </div>
    <div class="card-body">
        <code>{{ url('/xml/'.$dealer->xml_token.'.xml') }}</code>
        <p class="text-muted small mt-2 mb-0">Bu linki entegratörünüze veya pazaryeri araçlarınıza ekleyerek ürünleri otomatik çekebilirsiniz. Stok ve fiyatlar anlık güncellenir.</p>
    </div>
</div>

<div class="card">
    <div class="card-header bg-white fw-semibold">Son Siparişler</div>
    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead><tr><th>#</th><th>Müşteri</th><th>Tutar</th><th>Durum</th><th>Tarih</th></tr></thead>
            <tbody>
            @forelse($recentOrders as $o)
                <tr>
                    <td><a href="{{ route('dealer.orders.show', $o) }}">{{ $o->order_number }}</a></td>
                    <td>{{ $o->customer_name }}</td>
                    <td>{{ number_format($o->total, 2) }} ₺</td>
                    <td>{{ $o->status }}</td>
                    <td>{{ $o->created_at->format('d.m.Y H:i') }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center text-muted">Henüz sipariş yok</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
