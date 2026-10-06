@extends('layouts.app')
@section('title', 'Siparişlerim')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0">Siparişlerim</h4>
    <a href="{{ route('dealer.orders.create') }}" class="btn btn-primary btn-sm">
        <i class="bi bi-plus"></i> Yeni Sipariş
    </a>
</div>
<div class="card">
    <div class="table-responsive">
        <table class="table mb-0">
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
            @foreach($orders as $o)
                <tr>
                    <td>{{ $o->order_number }}</td>
                    <td>{{ $o->customer_name }}</td>
                    <td>{{ number_format($o->total, 2) }} ₺</td>
                    <td><span class="badge bg-secondary">{{ $o->status }}</span></td>
                    <td>{{ $o->tracking_number ?? '-' }}</td>
                    <td>{{ $o->created_at->format('d.m.Y H:i') }}</td>
                    <td><a href="{{ route('dealer.orders.show', $o) }}" class="btn btn-sm btn-outline-primary">Detay</a></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="card-footer">{{ $orders->links() }}</div>
</div>
@endsection
