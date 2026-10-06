@extends('layouts.app')
@section('title', 'Kara Liste')
@section('content')
<div class="mb-3"><h4 class="mb-1">Kara liste</h4><div class="text-muted small">Eşleşen müşteri ve ürün kodlarının sipariş edilmesini engeller.</div></div>
<div class="card mb-3">
    <div class="card-header bg-white fw-semibold">Yeni kayıt ekle</div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.blacklist.store') }}" class="row g-2 align-items-end">
            @csrf
            <div class="col-md-2"><label class="form-label">Tür</label><select name="type" class="form-select"><option value="product">Ürün kodu / barkod</option><option value="customer">Müşteri telefon / e-posta</option></select></div>
            <div class="col-md-4"><label class="form-label">Kod veya iletişim bilgisi</label><input name="value" class="form-control" required maxlength="255"></div>
            <div class="col-md-4"><label class="form-label">Sebep</label><input name="reason" class="form-control" maxlength="255"></div>
            <div class="col-md-2"><button class="btn btn-primary w-100">Listeye ekle</button></div>
        </form>
    </div>
</div>
<div class="card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>TÜR</th><th>KOD / BİLGİ</th><th>SEBEP</th><th>EKLENME</th><th></th></tr></thead>
            <tbody>
            @forelse($entries as $entry)
                <tr>
                    <td>{{ $entry->type === 'product' ? 'Ürün' : 'Müşteri' }}</td>
                    <td><code>{{ $entry->value }}</code></td>
                    <td>{{ $entry->reason ?: '—' }}</td>
                    <td>{{ $entry->created_at->format('d.m.Y H:i') }}</td>
                    <td><form method="POST" action="{{ route('admin.blacklist.destroy', $entry) }}">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Kaldır</button></form></td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center text-muted py-5">Kara listede kayıt yok.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white">{{ $entries->links() }}</div>
</div>
@endsection
