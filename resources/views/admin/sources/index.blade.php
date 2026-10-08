@extends('layouts.app')
@section('title', 'XML Kaynakları')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0">XML Kaynakları</h4>
    <a href="{{ route('admin.sources.create') }}" class="btn btn-primary btn-sm">
        <i class="bi bi-plus"></i> Yeni Kaynak
    </a>
</div>

<div class="card mb-4">
    <div class="card-header bg-white fw-semibold">Kaynaklar</div>
    <div class="table-responsive">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>Ad</th>
                    <th>Tip</th>
                    <th>XML bağlantısı</th>
                    <th>Ürün</th>
                    <th>Son Import</th>
                    <th>Durum</th>
                    <th>İşlem</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @foreach($sources as $source)
                <tr>
                    <td>{{ $source->name }}</td>
                    <td>{{ $source->type === 'url' ? 'Bağlantı' : 'Dosya' }}</td>
                    <td class="text-break" style="max-width:260px">{{ $source->url ?? '-' }}</td>
                    <td>{{ $source->products_count }}</td>
                    <td><span class="badge text-bg-light">Kâr %{{ number_format($source->xml_margin_percent ?? 0, 1) }}</span> <span class="badge text-bg-light">KDV %{{ number_format($source->tax_rate ?? 0, 1) }}</span></td>
                    <td>{{ $source->last_imported_at?->diffForHumans() ?? '-' }}</td>
                    <td>
                        <a href="{{ route('admin.sources.edit', $source) }}" class="btn btn-sm btn-outline-secondary">Ayarlar</a>
                        @if($source->is_active)
                            <span class="badge bg-success">Aktif</span>
                        @else
                            <span class="badge bg-secondary">Pasif</span>
                        @endif
                    </td>
                    <td>
                        @if($source->type === 'url')
                            <form action="{{ route('admin.sources.refresh', $source) }}" method="POST">
                                @csrf
                                <button class="btn btn-sm btn-outline-primary">Bağlantıdan güncelle</button>
                            </form>
                        @else
                            <form action="{{ route('admin.sources.upload', $source) }}" method="POST" enctype="multipart/form-data" class="d-flex gap-1">
                                @csrf
                                <input type="file" name="xml_file" accept=".xml,.txt" class="form-control form-control-sm" required style="max-width:180px">
                                <button class="btn btn-sm btn-outline-primary">Yükle</button>
                            </form>
                        @endif
                    </td>
                    <td>
                        <form action="{{ route('admin.sources.destroy', $source) }}" method="POST" onsubmit="return confirm('Bu XML kaynağı ve ona bağlı TÜM ürünler silinecek. Emin misiniz?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <div class="card-header bg-white fw-semibold">Son Import Geçmişi</div>
    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead>
                <tr>
                    <th>Kaynak</th>
                    <th>Dosya</th>
                    <th>Durum</th>
                    <th>Yeni</th>
                    <th>Güncellenen</th>
                    <th>Hata</th>
                    <th>Tarih</th>
                </tr>
            </thead>
            <tbody>
            @foreach($imports as $imp)
                <tr>
                    <td>{{ $imp->source->name ?? '-' }}</td>
                    <td>{{ $imp->file_name }}</td>
                    <td><span class="badge bg-{{ $imp->status === 'completed' ? 'success' : ($imp->status === 'failed' ? 'danger' : 'warning') }}">{{ $imp->status }}</span></td>
                    <td>{{ $imp->created_count }}</td>
                    <td>{{ $imp->updated_count }}</td>
                    <td>{{ $imp->error_count }}</td>
                    <td>{{ $imp->created_at->format('d.m.Y H:i') }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
