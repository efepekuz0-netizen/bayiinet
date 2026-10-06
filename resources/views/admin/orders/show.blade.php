@extends('layouts.app')
@section('title', $order->order_number)
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0">{{ $order->order_number }}</h4>
    <span class="badge bg-secondary fs-6">{{ $order->status }}</span>
</div>

<div class="row g-3">
    <div class="col-md-8">
        <div class="card mb-3">
            <div class="card-header bg-white fw-semibold">Ürünler</div>
            <table class="table mb-0">
                <thead><tr><th>Ürün</th><th>Adet</th><th>Birim</th><th>Toplam</th></tr></thead>
                <tbody>
                @foreach($order->items as $item)
                    <tr>
                        <td>{{ $item->product_title }} @if($item->variant_text)<small class="text-muted">— {{ $item->variant_text }}</small>@endif <small class="text-muted">({{ $item->stock_code }})</small></td>
                        <td>{{ $item->quantity }}</td>
                        <td>{{ number_format($item->unit_price, 2) }} ₺</td>
                        <td>{{ number_format($item->total_price, 2) }} ₺</td>
                    </tr>
                @endforeach
                </tbody>
                <tfoot>
                    <tr><td colspan="3" class="text-end fw-semibold">Toplam</td><td class="fw-bold">{{ number_format($order->total, 2) }} ₺</td></tr>
                </tfoot>
            </table>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card mb-3">
            <div class="card-header bg-white fw-semibold">Müşteri</div>
            <div class="card-body">
                <p class="mb-1"><strong>{{ $order->customer_name }}</strong></p>
                <p class="mb-1">{{ $order->customer_phone }}</p>
                <p class="mb-1">{{ $order->customer_city }} / {{ $order->customer_district }}</p>
                <p class="mb-0">{{ $order->customer_address }}</p>
            </div>
        </div>
        <div class="card">
            <div class="card-header bg-white fw-semibold">Durum Güncelle</div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.orders.status', $order) }}">
                    @csrf @method('PUT')
                    <div class="mb-2">
                        <select name="status" class="form-select form-select-sm">
                            @php
                                $availableTransitions = [
                                    'pending' => ['pending', 'paid', 'preparing', 'cancelled'],
                                    'paid' => ['paid', 'preparing', 'cancelled'],
                                    'preparing' => ['preparing', 'shipped', 'cancelled'],
                                    'shipped' => ['shipped', 'delivered', 'returned'],
                                    'delivered' => ['delivered', 'returned'],
                                    'cancelled' => ['cancelled'],
                                    'returned' => ['returned'],
                                ];
                            @endphp
                            @foreach($availableTransitions[$order->status] ?? [$order->status] as $s)
                                <option value="{{ $s }}" @selected($order->status === $s)>{{ $s }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-2">
                        <input type="text" name="cargo_company" class="form-control form-control-sm" placeholder="Kargo firması" value="{{ $order->cargo_company }}">
                    </div>
                    <div class="mb-2">
                        <input type="text" name="tracking_number" class="form-control form-control-sm" placeholder="Takip no" value="{{ $order->tracking_number }}">
                    </div>
                    <div class="mb-2">
                        <textarea name="admin_note" class="form-control form-control-sm" rows="2" placeholder="Not">{{ $order->admin_note }}</textarea>
                    </div>
                    <button class="btn btn-primary btn-sm w-100">Güncelle</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
