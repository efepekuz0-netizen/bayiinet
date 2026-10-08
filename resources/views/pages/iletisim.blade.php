@extends('layouts.app')
@section('title', 'İletişim')
@section('content')
<div class="card border-0 shadow-sm">
    <div class="card-body p-4">
        <h4 class="mb-3">İletişim</h4>
        <p class="mb-0 text-secondary" style="line-height:1.7">Destek ve iş birliği için panel üzerinden veya kayıtlı e-posta adresinizden bize ulaşabilirsiniz.</p>
        <p class="small text-muted mt-3 mb-0">Son güncelleme: {{ now()->format('d.m.Y') }}</p>
    </div>
</div>
@endsection
