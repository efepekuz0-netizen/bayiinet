@extends('layouts.app')
@section('title', $dealer->company_name)
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0">{{ $dealer->company_name }}</h4>
    <div class="d-flex gap-2">
        <a href="{{ route('admin.dealers.trendyol', $dealer) }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-shop me-1"></i> Trendyol'a Ürün Gönder</a>
        @if($dealer->status === 'pending')
            <form method="POST" action="{{ route('admin.dealers.approve', $dealer) }}">@csrf
                <button class="btn btn-success btn-sm">Onayla</button>
            </form>
        @endif
        @if($dealer->status === 'active')
            <form method="POST" action="{{ route('admin.dealers.suspend', $dealer) }}">@csrf
                <button class="btn btn-warning btn-sm">Askıya Al</button>
            </form>
        @endif
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card p-3">
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
        <div class="card p-3">
            <div class="text-muted small">XML Link</div>
            <code class="small d-block mt-1" style="word-break:break-all">{{ url('/xml/'.$dealer->xml_token.'.xml') }}</code>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card p-3">
            <div class="text-muted small">Yetkili / Telefon</div>
            <div>{{ $dealer->user->name ?? '-' }}</div>
            <div>{{ $dealer->phone }}</div>
            <div>{{ $dealer->city }} / {{ $dealer->district }}</div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header bg-white fw-semibold">Son Siparişler</div>
    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead><tr><th>#</th><th>Müşteri</th><th>Tutar</th><th>Durum</th><th>Tarih</th></tr></thead>
            <tbody>
            @foreach($dealer->orders as $o)
                <tr>
                    <td><a href="{{ route('admin.orders.show', $o) }}">{{ $o->order_number }}</a></td>
                    <td>{{ $o->customer_name }}</td>
                    <td>{{ number_format($o->total, 2) }} ₺</td>
                    <td>{{ $o->status }}</td>
                    <td>{{ $o->created_at->format('d.m.Y H:i') }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
