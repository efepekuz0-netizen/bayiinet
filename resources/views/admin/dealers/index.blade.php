@extends('layouts.app')
@section('title', 'Bayiler')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0">Bayiler</h4>
    <div class="btn-group btn-group-sm">
        <a href="?status=" class="btn btn-outline-secondary {{ !request('status') ? 'active' : '' }}">Tümü</a>
        <a href="?status=pending" class="btn btn-outline-warning {{ request('status')=='pending' ? 'active' : '' }}">Bekleyen</a>
        <a href="?status=active" class="btn btn-outline-success {{ request('status')=='active' ? 'active' : '' }}">Aktif</a>
        <a href="?status=suspended" class="btn btn-outline-danger {{ request('status')=='suspended' ? 'active' : '' }}">Askıda</a>
    </div>
</div>
<div class="card">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>Firma</th>
                    <th>Yetkili</th>
                    <th>Şehir</th>
                    <th>Bakiye</th>
                    <th>Durum</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @foreach($dealers as $d)
                <tr>
                    <td>{{ $d->company_name }}</td>
                    <td>{{ $d->user->name ?? '-' }}</td>
                    <td>{{ $d->city }}</td>
                    <td>{{ number_format($d->balance, 2) }} ₺</td>
                    <td>
                        @php $colors = ['pending'=>'warning','active'=>'success','suspended'=>'danger','rejected'=>'secondary']; @endphp
                        <span class="badge bg-{{ $colors[$d->status] ?? 'secondary' }}">{{ $d->status }}</span>
                    </td>
                    <td><a href="{{ route('admin.dealers.show', $d) }}" class="btn btn-sm btn-outline-primary">Detay</a></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="card-footer">{{ $dealers->links() }}</div>
</div>
@endsection
