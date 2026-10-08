@extends('layouts.app')
@section('title', 'Siparişlerim')
@section('content')
@php
    $statusLabels = [
        'pending' => 'Bekliyor',
        'paid' => 'Ödendi',
        'preparing' => 'Hazırlanıyor',
        'shipped' => 'Kargoda',
        'delivered' => 'Teslim',
        'cancelled' => 'İptal',
        'returned' => 'İade',
    ];
    $statusColors = [
        'pending' => 'secondary',
        'paid' => 'info',
        'preparing' => 'primary',
        'shipped' => 'warning',
        'delivered' => 'success',
        'cancelled' => 'dark',
        'returned' => 'danger',
    ];
@endphp
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-0">Siparişlerim</h4>
        <div class="text-muted small">Bakiye: <strong>{{ number_format(auth()->user()->dealer->balance, 2, ',', '.') }} ₺</strong></div>
    </div>
    <a href="{{ route('dealer.orders.create') }}" class="btn btn-primary btn-sm">
        <i class="bi bi-plus"></i> Yeni Sipariş
    </a>
</div>
<div class="card">
    <div class="table-responsive">
        <table class="table mb-0 align-middle">
            <thead>
                <tr>
                    <th>Sipariş No</th>
                    <th>Müşteri</th>
                    <th>Tutar</th>
                    <th>Durum</th>
                    <th>Kargo</th>
                    <th>Tarih</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse($orders as $o)
                <tr>
                    <td><code>{{ $o->order_number }}</code></td>
                    <td>{{ $o->customer_name }}</td>
                    <td class="fw-semibold">{{ number_format($o->total, 2, ',', '.') }} ₺</td>
                    <td><span class="badge text-bg-{{ $statusColors[$o->status] ?? 'secondary' }}">{{ $statusLabels[$o->status] ?? $o->status }}</span></td>
                    <td class="small">{{ $o->tracking_number ?? '—' }}</td>
                    <td class="small text-muted">{{ $o->created_at->format('d.m.Y H:i') }}</td>
                    <td><a href="{{ route('dealer.orders.show', $o) }}" class="btn btn-sm btn-outline-primary">Detay</a></td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-4">Henüz sipariş yok.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer">{{ $orders->links() }}</div>
</div>
@endsection
