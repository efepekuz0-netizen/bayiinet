@extends('layouts.app')
@section('title', 'Bayiler')
@section('content')
@php
    $statusMap = [
        'pending' => ['Bekliyor', 'warning'],
        'active' => ['Aktif', 'success'],
        'suspended' => ['Askıda', 'dark'],
        'rejected' => ['Red', 'danger'],
    ];
@endphp
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h4 class="mb-0">Bayiler</h4>
        <div class="text-muted small">Onay · bakiye · XML · Trendyol</div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-9 order-2 order-lg-1">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold">Bayi Listesi</div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Firma</th>
                            <th>Yetkili / E-posta</th>
                            <th>Şehir</th>
                            <th>Durum</th>
                            <th class="text-end">Bakiye</th>
                            <th>Kayıt</th>
                            <th class="text-end">İşlem</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($dealers as $dealer)
                        @php [$stLabel, $stColor] = $statusMap[$dealer->status] ?? [$dealer->status, 'secondary']; @endphp
                        <tr>
                            <td class="fw-semibold">
                                <a href="{{ route('admin.dealers.show', $dealer) }}" class="text-decoration-none">{{ $dealer->company_name }}</a>
                            </td>
                            <td>
                                <div>{{ $dealer->user->name ?? '—' }}</div>
                                <div class="small text-muted">{{ $dealer->user->email ?? '' }}</div>
                            </td>
                            <td>{{ $dealer->city ?: '—' }}</td>
                            <td><span class="badge text-bg-{{ $stColor }}">{{ $stLabel }}</span></td>
                            <td class="text-end">{{ number_format((float)$dealer->balance, 2, ',', '.') }} ₺</td>
                            <td class="small text-muted">{{ $dealer->created_at?->format('d.m.Y') }}</td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('admin.dealers.show', $dealer) }}" class="btn btn-sm btn-outline-primary" title="Detay"><i class="bi bi-eye"></i></a>
                                @if($dealer->status === 'pending')
                                    <form method="POST" action="{{ route('admin.dealers.approve', $dealer) }}" class="d-inline">
                                        @csrf
                                        <button class="btn btn-sm btn-success" title="Onayla" onclick="return confirm('Bu bayiyi onaylamak istiyor musunuz?')">
                                            <i class="bi bi-check-lg"></i> Onayla
                                        </button>
                                    </form>
                                @endif
                                @if($dealer->status === 'active')
                                    <a href="{{ route('admin.dealers.trendyol', $dealer) }}" class="btn btn-sm btn-outline-secondary" title="Trendyol"><i class="bi bi-shop"></i></a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-5">Kayıt bulunamadı.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if($dealers->hasPages())
                <div class="card-footer bg-white">{{ $dealers->links() }}</div>
            @endif
        </div>
    </div>
    <div class="col-lg-3 order-1 order-lg-2">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-funnel me-1"></i> Filtreler</div>
            <div class="card-body">
                <form method="GET">
                    <div class="mb-2">
                        <label class="form-label small">Ara</label>
                        <input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Firma, e-posta, şehir">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small">Durum</label>
                        <select name="status" class="form-select form-select-sm">
                            <option value="">Tümü</option>
                            <option value="pending" @selected(request('status')==='pending')>Bekliyor</option>
                            <option value="active" @selected(request('status')==='active')>Aktif</option>
                            <option value="suspended" @selected(request('status')==='suspended')>Askıda</option>
                        </select>
                    </div>
                    <button class="btn btn-primary btn-sm w-100 mb-2">Filtrele</button>
                    <a href="{{ route('admin.dealers.index') }}" class="btn btn-outline-secondary btn-sm w-100">Temizle</a>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
