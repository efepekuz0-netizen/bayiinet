@extends('layouts.app')
@section('title', 'Siparişler')
@section('content')
@php
    $statusLabels = [
        'pending' => ['Bekliyor', 'warning'],
        'paid' => ['Ödendi', 'info'],
        'preparing' => ['Hazırlanıyor', 'primary'],
        'shipped' => ['Kargoda', 'secondary'],
        'delivered' => ['Teslim', 'success'],
        'cancelled' => ['İptal', 'dark'],
        'returned' => ['İade', 'danger'],
    ];
@endphp
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h4 class="mb-0">Siparişler</h4>
</div>
<div class="row g-3">
    <div class="col-lg-9 order-2 order-lg-1">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold">Sipariş Listesi</div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Sipariş No</th>
                            <th>Bayi</th>
                            <th>Müşteri</th>
                            <th class="text-end">Tutar</th>
                            <th>Durum</th>
                            <th>Tarih</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($orders as $o)
                        @php [$lab, $col] = $statusLabels[$o->status] ?? [$o->status, 'secondary']; @endphp
                        <tr>
                            <td class="fw-semibold">{{ $o->order_number }}</td>
                            <td>{{ $o->dealer->company_name ?? '—' }}</td>
                            <td>{{ $o->customer_name }}</td>
                            <td class="text-end">{{ number_format((float)$o->total, 2, ',', '.') }} ₺</td>
                            <td><span class="badge text-bg-{{ $col }}">{{ $lab }}</span></td>
                            <td class="small text-muted">{{ $o->created_at->format('d.m.Y H:i') }}</td>
                            <td class="text-end">
                                <a href="{{ route('admin.orders.show', $o) }}" class="btn btn-sm btn-primary"><i class="bi bi-eye"></i></a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-5">Sipariş yok.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if($orders->hasPages())
                <div class="card-footer bg-white">{{ $orders->links() }}</div>
            @endif
        </div>
    </div>
    <div class="col-lg-3 order-1 order-lg-2">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-funnel me-1"></i> Filtre</div>
            <div class="card-body">
                <form method="GET">
                    <div class="mb-2">
                        <label class="form-label small">Sipariş / müşteri</label>
                        <input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="No veya isim">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small">Durum</label>
                        <select name="status" class="form-select form-select-sm">
                            <option value="">Tümü</option>
                            @foreach($statusLabels as $k => [$lab, $col])
                                <option value="{{ $k }}" @selected(request('status')===$k)>{{ $lab }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button class="btn btn-primary btn-sm w-100 mb-2">Filtrele</button>
                    <a href="{{ route('admin.orders.index') }}" class="btn btn-outline-secondary btn-sm w-100">Temizle</a>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
