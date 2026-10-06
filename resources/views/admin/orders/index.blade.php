@extends('layouts.app')
@section('title', 'Siparişler')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0">Siparişler</h4>
    <div class="btn-group btn-group-sm">
        <a href="?" class="btn btn-outline-secondary">Tümü</a>
        <a href="?status=paid" class="btn btn-outline-primary">Ödendi</a>
        <a href="?status=preparing" class="btn btn-outline-warning">Hazırlanıyor</a>
        <a href="?status=shipped" class="btn btn-outline-info">Kargoda</a>
        <a href="?status=delivered" class="btn btn-outline-success">Teslim</a>
    </div>
</div>
<div class="card">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>Sipariş No</th>
                    <th>Bayi</th>
                    <th>Müşteri</th>
                    <th>Tutar</th>
                    <th>Durum</th>
                    <th>Tarih</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @foreach($orders as $o)
                <tr>
                    <td>{{ $o->order_number }}</td>
                    <td>{{ $o->dealer->company_name ?? '-' }}</td>
                    <td>{{ $o->customer_name }}</td>
                    <td>{{ number_format($o->total, 2) }} ₺</td>
                    <td><span class="badge bg-secondary">{{ $o->status }}</span></td>
                    <td>{{ $o->created_at->format('d.m.Y H:i') }}</td>
                    <td><a href="{{ route('admin.orders.show', $o) }}" class="btn btn-sm btn-outline-primary">Detay</a></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="card-footer">{{ $orders->links() }}</div>
</div>
@endsection
