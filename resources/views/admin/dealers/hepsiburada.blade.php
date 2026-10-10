@extends('layouts.app')
@section('title', 'Hepsiburada · '.$dealer->company_name)
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h4 class="mb-0">Hepsiburada</h4>
        <div class="text-muted small">{{ $dealer->company_name }}</div>
    </div>
    <a href="{{ route('admin.dealers.show', $dealer) }}" class="btn btn-sm btn-outline-secondary">Bayiye dön</a>
</div>

@if($dealer->hepsiburada_last_error)
    <div class="alert alert-warning">Son hata: {{ $dealer->hepsiburada_last_error }}</div>
@endif

<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white fw-semibold">Bağlantı bilgileri</div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.dealers.hepsiburada.connection', $dealer) }}">
            @csrf @method('PUT')
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Merchant ID</label>
                    <input type="text" name="hepsiburada_merchant_id" class="form-control" value="{{ old('hepsiburada_merchant_id', $dealer->hepsiburada_merchant_id) }}" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Kullanıcı adı</label>
                    <input type="text" name="hepsiburada_username" class="form-control" placeholder="{{ $dealer->hasHepsiburadaCredentials() ? '•••• (kayıtlı)' : '' }}" autocomplete="off">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Şifre / Servis anahtarı</label>
                    <input type="password" name="hepsiburada_password" class="form-control" placeholder="{{ $dealer->hasHepsiburadaCredentials() ? '•••• (kayıtlı)' : '' }}" autocomplete="new-password">
                </div>
            </div>
            <div class="mt-3 d-flex gap-2">
                <button class="btn btn-primary">Kaydet</button>
            </div>
        </form>
        <form method="POST" action="{{ route('admin.dealers.hepsiburada.test', $dealer) }}" class="mt-2">
            @csrf
            <button class="btn btn-outline-secondary btn-sm" @disabled(! $dealer->hasHepsiburadaCredentials())>Bağlantıyı test et</button>
        </form>
    </div>
</div>

<div class="alert alert-info small mb-0">
    <strong>Not:</strong> Hepsiburada ürün gönderimi iskeleti eklendi. Tam katalog aktarımı (kategori/özellik eşlemesi)
    Trendyol akışı stabilize edildikten sonra aynı batch modeliyle genişletilecek.
    Merchant paneli → Entegrasyon bilgilerinden kullanıcı adı ve şifreyi alın.
</div>
@endsection
