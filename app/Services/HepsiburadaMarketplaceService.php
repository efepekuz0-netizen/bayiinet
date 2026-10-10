<?php

namespace App\Services;

use App\Models\Dealer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Hepsiburada Marketplace entegrasyonu (temel iskelet).
 *
 * Dokümantasyon: https://developers.hepsiburada.com/
 * Auth: Basic (username:password) + merchantId
 * Katalog: listing-external / mpop API
 */
class HepsiburadaMarketplaceService
{
    private function baseUrl(): string
    {
        return rtrim((string) config('bayiinet.hepsiburada.base_url', 'https://mpop.hepsiburada.com'), '/');
    }

    private function authHeader(Dealer $dealer): string
    {
        $c = $dealer->hepsiburada_credentials ?? [];
        $user = (string) ($c['username'] ?? '');
        $pass = (string) ($c['password'] ?? '');

        return 'Basic '.base64_encode($user.':'.$pass);
    }

    public function testConnection(Dealer $dealer): array
    {
        if (! $dealer->hasHepsiburadaCredentials()) {
            throw new RuntimeException('Hepsiburada merchant ID, kullanıcı adı ve şifre gerekli.');
        }

        $merchantId = $dealer->hepsiburada_merchant_id;
        $response = Http::withHeaders([
            'Authorization' => $this->authHeader($dealer),
            'Accept' => 'application/json',
            'User-Agent' => 'bayiinet/1.0',
        ])
            ->timeout(20)
            ->get($this->baseUrl().'/product/api/products/all-products-of-merchant/'.$merchantId, [
                'page' => 0,
                'size' => 1,
            ]);

        if ($response->status() === 401 || $response->status() === 403) {
            throw new RuntimeException('Hepsiburada kimlik doğrulama başarısız (401/403). Kullanıcı/şifre kontrol edin.');
        }

        // 404/400 de bağlantı kurulduğu anlamına gelebilir (endpoint sürüm farkı)
        if ($response->serverError()) {
            throw new RuntimeException('Hepsiburada sunucu hatası: HTTP '.$response->status());
        }

        return [
            'ok' => true,
            'status' => $response->status(),
            'message' => 'Bağlantı yanıt verdi (HTTP '.$response->status().').',
        ];
    }

    /**
     * Ürün gönderme iskeleti — HB listing formatına map.
     * Tam kategori/özellik eşlemesi sonraki iterasyonda genişletilir.
     *
     * @param  list<array<string, mixed>>  $items
     */
    public function createListings(Dealer $dealer, array $items): array
    {
        if (! $dealer->hasHepsiburadaCredentials()) {
            throw new RuntimeException('Hepsiburada bilgileri eksik.');
        }

        $merchantId = $dealer->hepsiburada_merchant_id;
        $payload = [
            'merchantId' => $merchantId,
            'items' => $items,
        ];

        $response = Http::withHeaders([
            'Authorization' => $this->authHeader($dealer),
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'User-Agent' => 'bayiinet/1.0',
        ])
            ->timeout(60)
            ->post($this->baseUrl().'/product/api/products/import', $payload);

        Log::info('Hepsiburada createListings', [
            'dealer_id' => $dealer->id,
            'status' => $response->status(),
            'body' => mb_substr($response->body(), 0, 500),
        ]);

        if ($response->failed()) {
            throw new RuntimeException('Hepsiburada ürün gönderimi başarısız: HTTP '.$response->status().' '.mb_substr($response->body(), 0, 200));
        }

        return $response->json() ?? ['raw' => $response->body()];
    }
}
