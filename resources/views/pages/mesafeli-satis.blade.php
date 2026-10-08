@extends('layouts.app')
@section('title', 'Mesafeli Satış Sözleşmesi')
@section('content')
<div class="card border-0 shadow-sm">
    <div class="card-body p-4">
        <h4 class="mb-3">Mesafeli Satış Sözleşmesi</h4>
        <p class="mb-0 text-secondary" style="line-height:1.7">Bayi ve platform arasında mesafeli satış ilişkisi, sipariş onayı ile kurulur. Ürün bedeli bakiyeden düşülür; kargo ve teslimat süreçleri platform politikalarına tabidir.</p>
        <p class="small text-muted mt-3 mb-0">Son güncelleme: {{ now()->format('d.m.Y') }}</p>
    </div>
</div>
@endsection
