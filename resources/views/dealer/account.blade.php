@extends('layouts.app')
@section('title', 'Hesabım')
@section('content')
<div class="mb-3">
    <h4 class="mb-1">Hesabım</h4>
    <p class="text-muted small mb-0">{{ $dealer->company_name }} · {{ auth()->user()->email }}</p>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if($errors->any())
    <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold">Firma / İletişim</div>
            <div class="card-body">
                <form method="POST" action="{{ route('dealer.account.profile') }}">
                    @csrf
                    @method('PUT')
                    <div class="mb-2">
                        <label class="form-label">Yetkili adı</label>
                        <input type="text" name="name" class="form-control" value="{{ old('name', auth()->user()->name) }}" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Telefon</label>
                        <input type="text" name="phone" class="form-control" value="{{ old('phone', $dealer->phone) }}">
                    </div>
                    <div class="row g-2">
                        <div class="col-6 mb-2">
                            <label class="form-label">Şehir</label>
                            <input type="text" name="city" class="form-control" value="{{ old('city', $dealer->city) }}">
                        </div>
                        <div class="col-6 mb-2">
                            <label class="form-label">İlçe</label>
                            <input type="text" name="district" class="form-control" value="{{ old('district', $dealer->district) }}">
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Adres</label>
                        <textarea name="address" class="form-control" rows="2">{{ old('address', $dealer->address) }}</textarea>
                    </div>
                    <div class="row g-2">
                        <div class="col-6 mb-2">
                            <label class="form-label">Vergi no</label>
                            <input type="text" name="tax_number" class="form-control" value="{{ old('tax_number', $dealer->tax_number) }}">
                        </div>
                        <div class="col-6 mb-2">
                            <label class="form-label">Vergi dairesi</label>
                            <input type="text" name="tax_office" class="form-control" value="{{ old('tax_office', $dealer->tax_office) }}">
                        </div>
                    </div>
                    <button class="btn btn-primary">Kaydet</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold">Şifre değiştir</div>
            <div class="card-body">
                <form method="POST" action="{{ route('dealer.account.password') }}">
                    @csrf
                    @method('PUT')
                    <div class="mb-2">
                        <label class="form-label">Mevcut şifre</label>
                        <input type="password" name="current_password" class="form-control" required autocomplete="current-password">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Yeni şifre</label>
                        <input type="password" name="password" class="form-control" required autocomplete="new-password">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Yeni şifre (tekrar)</label>
                        <input type="password" name="password_confirmation" class="form-control" required autocomplete="new-password">
                    </div>
                    <button class="btn btn-outline-primary">Şifreyi güncelle</button>
                </form>
            </div>
        </div>
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold">Vergi levhası</div>
            <div class="card-body">
                @if($dealer->tax_document_path)
                    <div class="alert alert-success py-2 small">Yüklü:
                        <a href="{{ asset('storage/'.$dealer->tax_document_path) }}" target="_blank" rel="noopener">Görüntüle</a>
                    </div>
                @else
                    <p class="text-muted small">PDF veya görsel (max 5 MB).</p>
                @endif
                <form method="POST" action="{{ route('dealer.account.tax') }}" enctype="multipart/form-data">
                    @csrf
                    <input type="file" name="tax_document" class="form-control mb-2" accept=".pdf,.jpg,.jpeg,.png" required>
                    <button class="btn btn-outline-secondary">Yükle</button>
                </form>
            </div>
        </div>
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold">XML Feed</div>
            <div class="card-body">
                <input class="form-control form-control-sm mb-2" id="xml-link" readonly value="{{ url('/xml/'.$dealer->xml_token.'.xml') }}">
                <button type="button" class="btn btn-sm btn-outline-primary" onclick="navigator.clipboard.writeText(document.getElementById('xml-link').value)">Kopyala</button>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mt-3">
    <div class="card-header bg-white fw-semibold d-flex justify-content-between">
        <span>Son siparişler</span>
        <a href="{{ route('dealer.orders.index') }}" class="small">Tümü</a>
    </div>
    <div class="table-responsive">
        <table class="table mb-0">
            <thead><tr><th>Sipariş</th><th>Müşteri</th><th>Tutar</th><th>Durum</th><th></th></tr></thead>
            <tbody>
            @forelse($recentOrders as $order)
                <tr>
                    <td>{{ $order->order_number }}</td>
                    <td>{{ $order->customer_name }}</td>
                    <td>{{ number_format($order->total, 2) }} ₺</td>
                    <td><span class="badge text-bg-secondary">{{ $order->status }}</span></td>
                    <td><a href="{{ route('dealer.orders.show', $order) }}" class="btn btn-sm btn-outline-primary">Detay</a></td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center text-muted py-4">Henüz sipariş yok.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
