@extends('layouts.app')
@section('title', 'Bayiler')
@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h4 class="mb-0">Bayiler</h4>
        <div class="text-muted small">Onay, bakiye, XML ve Trendyol yönetimi</div>
    </div>
    <form method="GET" class="d-flex gap-2 flex-wrap">
        @if(request('status'))
            <input type="hidden" name="status" value="{{ request('status') }}">
        @endif
        <input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Firma, e-posta, şehir…" style="min-width:200px">
        <button class="btn btn-sm btn-primary">Ara</button>
    </form>
</div>

<div class="btn-group btn-group-sm mb-3 flex-wrap">
    <a href="{{ route('admin.dealers.index') }}" class="btn btn-outline-secondary {{ !request('status') ? 'active' : '' }}">Tümü</a>
    <a href="{{ route('admin.dealers.index', ['status' => 'pending']) }}" class="btn btn-outline-warning {{ request('status')=='pending' ? 'active' : '' }}">Bekleyen</a>
    <a href="{{ route('admin.dealers.index', ['status' => 'active']) }}" class="btn btn-outline-success {{ request('status')=='active' ? 'active' : '' }}">Aktif</a>
    <a href="{{ route('admin.dealers.index', ['status' => 'suspended']) }}" class="btn btn-outline-danger {{ request('status')=='suspended' ? 'active' : '' }}">Askıda</a>
</div>

<div class="row g-3">
@forelse($dealers as $dealer)
    @php
        $statusMap = ['pending' => ['Bekliyor', 'warning'], 'active' => ['Aktif', 'success'], 'suspended' => ['Askıda', 'danger'], 'rejected' => ['Red', 'dark']];
        [$stLabel, $stColor] = $statusMap[$dealer->status] ?? [$dealer->status, 'secondary'];
    @endphp
    <div class="col-12 col-md-6 col-xl-4">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                    <div class="min-w-0">
                        <h6 class="mb-0 text-truncate">{{ $dealer->company_name }}</h6>
                        <div class="small text-muted text-truncate">{{ $dealer->user->name ?? '—' }} · {{ $dealer->user->email ?? '' }}</div>
                    </div>
                    <span class="badge text-bg-{{ $stColor }}">{{ $stLabel }}</span>
                </div>
                <div class="d-flex justify-content-between small mb-2">
                    <span class="text-muted">{{ $dealer->city ?: 'Şehir yok' }}</span>
                    <span class="fw-bold text-primary">{{ number_format((float) $dealer->balance, 2, ',', '.') }} ₺</span>
                </div>
                <div class="d-flex flex-wrap gap-1 mb-3">
                    @if($dealer->hasTrendyolCredentials())
                        <span class="badge text-bg-light border">Trendyol bağlı</span>
                    @else
                        <span class="badge text-bg-light border text-muted">Trendyol yok</span>
                    @endif
                    @if($dealer->auto_sync_enabled)
                        <span class="badge text-bg-light border">Oto senkron</span>
                    @endif
                </div>
                <div class="d-flex flex-wrap gap-1">
                    <a href="{{ route('admin.dealers.show', $dealer) }}" class="btn btn-sm btn-primary">Yönet</a>
                    <a href="{{ route('admin.dealers.trendyol', $dealer) }}" class="btn btn-sm btn-outline-secondary">Trendyol</a>
                    @if($dealer->status === 'pending')
                        <form method="POST" action="{{ route('admin.dealers.approve', $dealer) }}" class="d-inline">@csrf
                            <button class="btn btn-sm btn-success">Onayla</button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
@empty
    <div class="col-12"><div class="alert alert-info mb-0">Bayi bulunamadı.</div></div>
@endforelse
</div>
<div class="mt-3">{{ $dealers->links() }}</div>
@endsection
