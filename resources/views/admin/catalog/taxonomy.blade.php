@extends('layouts.app')
@section('title', 'Kategori & Marka')
@section('content')
<div class="mb-3"><h4 class="mb-1">Kategori & marka</h4><div class="text-muted small">Ürün XML'lerinden gelen kategori yolları ve markaları yönetin.</div></div>
<div class="row g-3">
    <div class="col-xl-5">
        <div class="card mb-3">
            <div class="card-header bg-white fw-semibold">Kategori ekle</div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.taxonomy.categories.store') }}">
                    @csrf
                    <div class="mb-2"><label class="form-label">Kategori adı</label><input name="name" class="form-control" required></div>
                    <div class="mb-2"><label class="form-label">Tam yol (isteğe bağlı)</label><input name="full_path" class="form-control" placeholder="Elektronik >>> Ses Sistemleri"></div>
                    <div class="mb-3"><label class="form-label">Üst kategori</label><select name="parent_id" class="form-select"><option value="">Ana kategori</option>@foreach($categories as $category)<option value="{{ $category->id }}">{{ $category->full_path ?: $category->name }}</option>@endforeach</select></div>
                    <button class="btn btn-primary">Kategori ekle</button>
                </form>
            </div>
        </div>
        <div class="card">
            <div class="card-header bg-white fw-semibold">Kategoriler</div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>KATEGORİ</th><th>ÜRÜN</th><th></th></tr></thead>
                    <tbody>
                    @forelse($categories as $category)
                        <tr><td>{{ $category->full_path ?: $category->name }}</td><td>{{ $category->products_count }}</td><td><form method="POST" action="{{ route('admin.taxonomy.categories.destroy', $category) }}">@csrf @method('DELETE')<button class="btn btn-sm btn-link text-danger">Sil</button></form></td></tr>
                    @empty
                        <tr><td colspan="3" class="text-center text-muted py-4">Henüz kategori tanımlanmamış.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-white">{{ $categories->links() }}</div>
        </div>
    </div>
    <div class="col-xl-7">
        <div class="card">
            <div class="card-header bg-white fw-semibold">Ürün markaları <span class="text-muted fw-normal">· katalogdan otomatik</span></div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th>MARKA</th><th>ÜRÜN SAYISI</th><th>MARKA ADINI DEĞİŞTİR</th></tr></thead>
                    <tbody>
                    @forelse($brands as $brand)
                        <tr>
                            <td class="fw-semibold">{{ $brand->brand }}</td><td>{{ $brand->products_count }}</td>
                            <td><form method="POST" action="{{ route('admin.taxonomy.brands.update') }}" class="d-flex gap-2">@csrf @method('PUT')<input type="hidden" name="current_brand" value="{{ $brand->brand }}"><input name="brand" class="form-control form-control-sm" value="{{ $brand->brand }}" required><button class="btn btn-sm btn-outline-primary">Kaydet</button></form></td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-center text-muted py-4">XML ürünlerinde henüz marka bulunamadı.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
