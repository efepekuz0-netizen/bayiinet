@extends('layouts.app')
@section('title', 'Bayilik Başvurusu')
@section('content')
<div class="container py-4 px-3">
    <div class="row justify-content-center">
        <div class="col-12 col-sm-10 col-md-7 col-lg-6">
            <div class="card shadow">
                <div class="card-body p-4">
                    <h3 class="text-center mb-4">Bayilik Başvurusu</h3>
                    @if($errors->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                        </div>
                    @endif
                    <form method="POST" action="{{ route('register') }}">
                        @csrf
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Ad Soyad</label>
                                <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Firma Adı</label>
                                <input type="text" name="company_name" class="form-control" value="{{ old('company_name') }}" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">E-posta</label>
                            <input type="email" name="email" class="form-control" value="{{ old('email') }}" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Şifre</label>
                                <input type="password" name="password" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Şifre Tekrar</label>
                                <input type="password" name="password_confirmation" class="form-control" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Telefon</label>
                                <input type="text" name="phone" class="form-control" value="{{ old('phone') }}">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Şehir</label>
                                <input type="text" name="city" class="form-control" value="{{ old('city') }}">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Başvuruyu Gönder</button>
                    </form>
                    <p class="text-center mt-3 mb-0">
                        Zaten hesabınız var mı? <a href="{{ route('login') }}">Giriş Yap</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
