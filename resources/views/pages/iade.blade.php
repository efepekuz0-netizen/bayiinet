@extends('layouts.app')
@section('title', 'İade ve Cayma')
@section('content')
<div class="card border-0 shadow-sm">
    <div class="card-body p-4">
        <h4 class="mb-3">İade ve Cayma</h4>
        <p class="mb-0 text-secondary" style="line-height:1.7">Cayma ve iade talepleri, ürünün kullanılmamış ve yeniden satılabilir olması koşuluyla değerlendirilir. Keyfi iadeler kabul edilmez. Detaylı süreç için destek ekibiyle iletişime geçin.</p>
        <p class="small text-muted mt-3 mb-0">Son güncelleme: {{ now()->format('d.m.Y') }}</p>
    </div>
</div>
@endsection
