@extends('layouts.app')
@section('title', 'Müşteriler')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div><h4 class="mb-1">Müşteriler</h4><div class="text-muted small">Siparişlerdeki son müşteri ve teslimat kayıtları.</div></div>
    <form class="d-flex gap-2"><input name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Ad, telefon veya e-posta"><button class="btn btn-sm btn-primary">Ara</button></form>
</div>
<div class="card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>MÜŞTERİ</th><th>TELEFON / E-POSTA</th><th>ŞEHİR</th><th>SİPARİŞ</th><th>TOPLAM HARCAMA</th><th>SON SİPARİŞ</th></tr></thead>
            <tbody>
            @forelse($customers as $customer)
                <tr>
                    <td class="fw-semibold">{{ $customer->customer_name }}</td>
                    <td>{{ $customer->customer_phone ?: '—' }}<div class="small text-muted">{{ $customer->customer_email }}</div></td>
                    <td>{{ $customer->customer_city ?: '—' }}</td>
                    <td>{{ $customer->order_count }}</td>
                    <td>{{ number_format($customer->total_spent, 2) }} ₺</td>
                    <td>{{ \Illuminate\Support\Carbon::parse($customer->last_order_at)->format('d.m.Y H:i') }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted py-5">Sipariş oluşturuldukça müşteriler burada görünür.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white">{{ $customers->links() }}</div>
</div>
@endsection
