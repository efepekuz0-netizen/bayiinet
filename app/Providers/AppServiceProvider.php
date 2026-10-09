<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Paginator::useBootstrapFive();

        // Tek merkezden şifre kuralı: kayıt, profil güncelleme ve yönetici
        // oluşturma aynı kuralı kullanır.
        Password::defaults(fn (): Password => Password::min(8));
    }
}
