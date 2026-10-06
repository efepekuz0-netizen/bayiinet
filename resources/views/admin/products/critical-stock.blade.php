@extends('layouts.app')
@section('title', 'Kritik Stok')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div><h4 class="mb-1">Kritik stok</h4><div class="text-muted small">Stok eşiği {{ $threshold }} ve altında olan aktif ürünler / varyantlar.</div></div>
    <a href="{{ route('admin.settings.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-sliders me-1"></i>Eşiği ayarla</a>
</div>
<div class="card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>ÜRÜN</th><th>STOK KODU</th><th>KAYNAK</th><th>STOK / VARYANTLAR</th><th>GÜNCELLEME</th></tr></thead>
            <tbody>
            @forelse($products as $product)
                <tr>
                    <td><a href="{{ route('admin.products.edit', $product) }}">{{ $product->title }}</a></td>
                    <td><code>{{ $product->stock_code }}</code></td>
                    <td>{{ $product->source->name ?? 'Manuel' }}</td>
                    <td>
                        @if($product->has_variants)
                            @foreach($product->variants->where('stock', '<=', $threshold) as $variant)
                                <span class="badge text-bg-warning me-1">{{ $variant->full_name }}: {{ $variant->stock }}</span>
                            @endforeach
                        @else
                            <span class="badge text-bg-warning">{{ $product->stock }}</span>
                        @endif
                    </td>
                    <td>{{ $product->last_synced_at?->diffForHumans() ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center text-muted py-5">Kritik seviyede ürün yok.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white">{{ $products->links() }}</div>
</div>
@endsection
