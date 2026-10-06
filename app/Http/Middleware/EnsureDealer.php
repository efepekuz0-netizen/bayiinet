<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDealer
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! auth()->check() || ! auth()->user()->isDealer()) {
            abort(403, 'Yetkisiz erişim.');
        }

        $dealer = auth()->user()->dealer;
        if (! $dealer) {
            abort(403, 'Bayi profili bulunamadı.');
        }

        if ($dealer->status !== 'active') {
            return redirect()->route('dealer.application');
        }

        return $next($request);
    }
}
