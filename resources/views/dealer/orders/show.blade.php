@extends('layouts.app')
@section('title', $order->order_number)
@section('content')
<h4 class="mb-4">{{ $order->order_number }} <span class="badge text-bg-{{ $order->status_tone }}">{{ $order->status_label }}</span></h4>

<div class="row g-3">
    <div class="col-md-7">
        <div class="card mb-3">
            <div class="card-header bg-white fw-semibold">Ürünler</div>
            <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead><tr><th>Ürün</th><th>Adet</th><th>Birim</th><th>Toplam</th></tr></thead>
                <tbody>
                @foreach($order->items as $item)
                    <tr>
                        <td>{{ $item->product_title }}</td>
                        <td>{{ $item->quantity }}</td>
                        <td>{{ number_format($item->unit_price, 2) }} ₺</td>
                        <td>{{ number_format($item->total_price, 2) }} ₺</td>
                    </tr>
                @endforeach
                </tbody>
                <tfoot>
                    <tr><td colspan="3" class="text-end fw-semibold">Toplam</td>
                    <td class="fw-bold">{{ number_format((float) $order->total, 2) }} ₺</td></tr>
                </tfoot>
            </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-truck me-1"></i> Kargo Etiketi & Ödeme
            </div>
            <div class="card-body">
                <p class="text-muted small">
                    Siparişi bize iletmek için kargo takip numarasını girin, isterseniz kargo PDF etiketini ve ödeme dekontunu yükleyin.
                    Onaydan sonra ürün müşterinize gönderilir.
                </p>
                <form method="POST" action="{{ route('dealer.orders.cargo', $order) }}" enctype="multipart/form-data">
                    @csrf
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label">Kargo Firması</label>
                            <input type="text" name="cargo_company" class="form-control" value="{{ $order->cargo_company }}" placeholder="Sürat, Yurtiçi, Aras...">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Takip No *</label>
                            <input type="text" name="tracking_number" class="form-control" value="{{ $order->tracking_number }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Kargo PDF Etiketi</label>
                            <input type="file" name="cargo_pdf" class="form-control" accept=".pdf">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Ödeme Dekontu</label>
                            <input type="file" name="payment_proof" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                        </div>
                    </div>
                    <button class="btn btn-primary mt-3">
                        <i class="bi bi-send me-1"></i> Kargo Bilgisini Gönder
                    </button>
                </form>
                @if($order->cargo_label_uploaded_at)
                    <div class="alert alert-success mt-3 mb-0 small">
                        Kargo bilgisi {{ $order->cargo_label_uploaded_at->format('d.m.Y H:i') }} tarihinde iletildi.
                    </div>
                @endif
            </div>
        </div>
    </div>
    <div class="col-md-5">
        <div class="card mb-3">
            <div class="card-header bg-white fw-semibold">Müşteri</div>
            <div class="card-body">
                <p class="mb-1"><strong>{{ $order->customer_name }}</strong></p>
                <p class="mb-1">{{ $order->customer_phone }}</p>
                <p class="mb-1">{{ $order->customer_city }} / {{ $order->customer_district }}</p>
                <p class="mb-0">{{ $order->customer_address }}</p>
            </div>
        </div>
        @if($order->tracking_number)
        <div class="card">
            <div class="card-header bg-white fw-semibold">Mevcut Kargo</div>
            <div class="card-body">
                <p class="mb-1">{{ $order->cargo_company }}</p>
                <p class="mb-0"><code>{{ $order->tracking_number }}</code></p>
            </div>
        </div>
        @endif
    </div>
</div>
@endsection
