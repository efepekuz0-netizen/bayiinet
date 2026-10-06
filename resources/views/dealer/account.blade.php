@extends('layouts.app')
@section('title', 'Hesabım')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div><h4 class="mb-1">Hesabım</h4><div class="text-muted small">Bayi profiliniz, bakiye bilgileriniz ve son siparişleriniz.</div></div>
    <a href="{{ route('home') }}" class="btn btn-primary"><i class="bi bi-bag-plus me-1"></i> Ürünlere git</a>
</div>
<div class="row g-3 mb-4">
    <div class="col-md-4"><div class="card p-3 h-100"><div class="text-muted small">Firma</div><div class="fs-5 fw-semibold">{{ $dealer->company_name }}</div><div class="small text-muted mt-1">{{ $dealer->user->name }} · {{ $dealer->user->email }}</div></div></div>
    <div class="col-md-4"><div class="card p-3 h-100"><div class="text-muted small">Bakiye</div><div class="fs-3 fw-bold text-success">{{ number_format($dealer->balance, 2) }} ₺</div><div class="small text-muted">Sipariş ödemeleri kargo bilgisi gönderilince düşülür.</div></div></div>
    <div class="col-md-4"><div class="card p-3 h-100"><div class="text-muted small">Hesap durumu</div><div class="fs-5"><span class="badge text-bg-{{ $dealer->isActive() ? 'success' : 'warning' }}">{{ $dealer->isActive() ? 'Aktif' : ucfirst($dealer->status) }}</span></div><div class="small text-muted mt-2">Üyelik başlangıcı: {{ optional($dealer->created_at)->format('d.m.Y') }}</div></div></div>
</div>
<div class="card mb-4"><div class="card-header bg-white fw-semibold">XML Feed bağlantınız</div><div class="card-body"><div class="input-group"><input id="xml-link" class="form-control" readonly value="{{ url('/xml/'.$dealer->xml_token.'.xml') }}"><button class="btn btn-outline-primary" onclick="navigator.clipboard.writeText(document.getElementById('xml-link').value)">Kopyala</button></div></div></div>
<div class="card"><div class="card-header bg-white fw-semibold d-flex justify-content-between"><span>Son siparişler</span><a href="{{ route('dealer.orders.index') }}" class="small">Tümünü gör</a></div><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Sipariş</th><th>Müşteri</th><th>Tutar</th><th>Durum</th><th></th></tr></thead><tbody>@forelse($recentOrders as $order)<tr><td>{{ $order->order_number }}</td><td>{{ $order->customer_name }}</td><td>{{ number_format($order->total, 2) }} ₺</td><td><span class="badge text-bg-secondary">{{ $order->status }}</span></td><td><a href="{{ route('dealer.orders.show', $order) }}" class="btn btn-sm btn-outline-primary">Detay</a></td></tr>@empty<tr><td colspan="5" class="text-center text-muted py-4">Henüz sipariş yok.</td></tr>@endforelse</tbody></table></div></div>
@endsection
