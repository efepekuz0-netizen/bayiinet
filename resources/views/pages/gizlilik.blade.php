@extends('layouts.app')
@section('title', 'Gizlilik Politikası')
@section('content')
<div class="card border-0 shadow-sm">
    <div class="card-body p-4">
        <h4 class="mb-3">Gizlilik Politikası</h4>
        <p class="mb-0 text-secondary" style="line-height:1.7">Bu site üzerinden toplanan kişisel veriler, sipariş ve üyelik süreçlerinin yürütülmesi amacıyla işlenir. Verileriniz üçüncü taraflarla paylaşılmaz; yasal zorunluluklar saklıdır.</p>
        <p class="small text-muted mt-3 mb-0">Son güncelleme: {{ now()->format('d.m.Y') }}</p>
    </div>
</div>
@endsection
