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
                    <th class="text-end">Ürün</th>
                    <th>Fiyatlama</th>
                    <th>Son içe aktarma</th>
                    <th>Durum</th>
                    <th>İşlem</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse($sources as $source)
                <tr>
                    <td>
                        <div class="fw-semibold">{{ $source->name }}</div>
                        @if($source->last_error)
                            <div class="small text-danger text-truncate" style="max-width:280px" title="{{ $source->last_error }}">
                                <i class="bi bi-exclamation-circle me-1"></i>{{ Str::limit($source->last_error, 70) }}
                            </div>
                        @endif
                    </td>
                    <td><span class="badge text-bg-light border">{{ $source->type === 'url' ? 'Bağlantı' : 'Dosya' }}</span></td>
                    <td class="text-break small" style="max-width:260px">{{ $source->url ?? '-' }}</td>
                    <td class="text-end">{{ number_format($source->products_count ?? 0) }}</td>
                    <td class="text-nowrap">
                        <span class="badge text-bg-light border">Kâr %{{ number_format($source->xml_margin_percent ?? 0, 1) }}</span>
                        <span class="badge text-bg-light border">KDV %{{ number_format($source->tax_rate ?? 0, 1) }}</span>
                    </td>
                    <td class="text-nowrap small">{{ $source->last_imported_at?->diffForHumans() ?? '—' }}</td>
                    <td>
                        @if($source->is_active)
                            <span class="badge text-bg-success">Aktif</span>
                        @else
                            <span class="badge text-bg-secondary">Pasif</span>
                        @endif
                    </td>
                    <td class="text-nowrap">
                        <a href="{{ route('admin.sources.edit', $source) }}" class="btn btn-sm btn-outline-secondary">Ayarlar</a>
                        @if($source->type === 'url')
                            <form action="{{ route('admin.sources.refresh', $source) }}" method="POST" class="d-inline">
                                @csrf
                                <button class="btn btn-sm btn-outline-primary">Bağlantıdan güncelle</button>
                            </form>
                        @else
                            <form action="{{ route('admin.sources.upload', $source) }}" method="POST" enctype="multipart/form-data" class="d-inline-flex gap-1 align-middle">
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
            @empty
                <tr><td colspan="9" class="text-center text-muted py-4">Henüz XML kaynağı yok.</td></tr>
            @endforelse
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
                    <td><span class="badge text-bg-{{ $imp->status_tone }}">{{ $imp->status_label }}</span></td>
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
