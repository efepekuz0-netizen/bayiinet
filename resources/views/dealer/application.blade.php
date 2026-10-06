@extends('layouts.app')
@section('title', 'Bayilik Başvuru Durumu')
@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-body p-4 p-md-5">
                    @if($dealer->status === 'pending')
                        <span class="badge text-bg-warning mb-3">İncelemede</span>
                        <h2>Bayilik başvurunuz alındı</h2>
                        <p class="text-muted mb-0">Başvurunuz yönetici tarafından inceleniyor. Onaylandığında ürün kataloğuna ve sipariş ekranına erişebilirsiniz.</p>
                    @elseif($dealer->status === 'suspended')
                        <span class="badge text-bg-danger mb-3">Hesap askıda</span>
                        <h2>Bayi hesabınız şu anda aktif değil</h2>
                        <p class="text-muted mb-0">Erişimin yeniden açılması için sistem yöneticisiyle iletişime geçin.</p>
                    @else
                        <span class="badge text-bg-secondary mb-3">Başvuru durumu</span>
                        <h2>Bayilik başvurunuz onaylanmadı</h2>
                        <p class="text-muted mb-0">Detaylı bilgi için sistem yöneticisiyle iletişime geçin.</p>
                    @endif
                    @if($dealer->admin_note)
                        <div class="alert alert-light border mt-4 mb-0">{{ $dealer->admin_note }}</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
