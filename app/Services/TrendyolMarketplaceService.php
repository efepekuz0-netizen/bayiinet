<?php

namespace App\Services;

use App\Exceptions\MarketplaceApiException;
use App\Models\MarketplaceConnection;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use LogicException;
use UnexpectedValueException;

class TrendyolMarketplaceService
{
    private const BASE_URL = 'https://apigw.trendyol.com';

    public function testConnection(MarketplaceConnection $connection): array
    {
        return $this->approvedInventoryPage($connection, [
            'page' => 0,
            'size' => 1,
        ]);
    }

    public function approvedInventoryPage(MarketplaceConnection $connection, array $query): array
    {
        $response = $this->get(
            $connection,
            $this->productPath($connection).'/products/approved/inventory-and-price',
            $query,
        );

        if (! isset($response['content']) || ! is_array($response['content'])) {
            throw new UnexpectedValueException('Trendyol ürün listesi beklenen biçimde değil.');
        }

        return $response;
    }

    /**
     * Ürün Oluşturma v2. Tek istekte en fazla 1000 kalem gönderilebilir.
     * Dönen yanıttaki batchRequestId ile sonuç sorgulanır.
     */
    public function createProducts(MarketplaceConnection $connection, array $items): array
    {
        return $this->post(
            $connection,
            $this->productPath($connection).'/v2/products',
            ['items' => array_values($items)],
        );
    }

    public function updatePriceAndInventory(MarketplaceConnection $connection, array $items): array
    {
        return $this->post(
            $connection,
            '/integration/inventory/sellers/'.rawurlencode($connection->account_id).'/products/price-and-inventory',
            ['items' => $items],
        );
    }

    public function batchResult(MarketplaceConnection $connection, string $batchRequestId): array
    {
        return $this->get(
            $connection,
            $this->productPath($connection).'/products/batch-requests/'.rawurlencode($batchRequestId),
        );
    }

    public function orderStream(MarketplaceConnection $connection, array $query): array
    {
        return $this->get(
            $connection,
            '/integration/order/sellers/'.rawurlencode($connection->account_id).'/orders/stream',
            $query,
        );
    }

    public function updatePackageStatus(
        MarketplaceConnection $connection,
        string $packageId,
        string $status,
        ?string $invoiceNumber = null,
    ): array {
        $payload = ['status' => $status];
        if ($status === 'Invoiced' && $invoiceNumber !== null) {
            $payload['params'] = ['invoiceNumber' => $invoiceNumber];
        }

        return $this->put(
            $connection,
            '/integration/order/sellers/'.rawurlencode($connection->account_id).'/shipment-packages/'.rawurlencode($packageId),
            $payload,
        );
    }

    private function productPath(MarketplaceConnection $connection): string
    {
        return '/integration/product/sellers/'.rawurlencode($connection->account_id);
    }

    private function get(MarketplaceConnection $connection, string $path, array $query = []): array
    {
        return $this->send($connection, 'GET', $path, $query);
    }

    private function post(MarketplaceConnection $connection, string $path, array $payload): array
    {
        return $this->send($connection, 'POST', $path, $payload);
    }

    private function put(MarketplaceConnection $connection, string $path, array $payload): array
    {
        return $this->send($connection, 'PUT', $path, $payload, allowEmptyResponse: true);
    }

    private function send(
        MarketplaceConnection $connection,
        string $method,
        string $path,
        array $data = [],
        bool $allowEmptyResponse = false,
    ): array {
        $credentials = $connection->credentials ?? [];
        $apiKey = $credentials['api_key'] ?? null;
        $apiSecret = $credentials['api_secret'] ?? null;

        if (! is_string($apiKey) || $apiKey === '' || ! is_string($apiSecret) || $apiSecret === '') {
            throw new LogicException('Trendyol API anahtarı ve sırrı kaydedilmemiş.');
        }

        $request = Http::withBasicAuth($apiKey, $apiSecret)
            ->withHeaders(['User-Agent' => $connection->account_id.' - SelfIntegration'])
            ->acceptJson()
            ->connectTimeout(10)
            ->timeout(60)
            ->withoutRedirecting();

        $response = match ($method) {
            'GET' => $request->get(self::BASE_URL.$path, $data),
            'POST' => $request->post(self::BASE_URL.$path, $data),
            'PUT' => $request->put(self::BASE_URL.$path, $data),
            default => throw new LogicException('Desteklenmeyen Trendyol API isteği.'),
        };

        return $this->decode($response, $allowEmptyResponse);
    }

    private function decode(Response $response, bool $allowEmptyResponse): array
    {
        if (! $response->successful()) {
            $message = match ($response->status()) {
                401, 403 => 'Trendyol kimlik doğrulaması başarısız. Mağaza numarası ve API bilgilerini kontrol edin.',
                429 => 'Trendyol istek sınırına ulaşıldı. Biraz bekleyip tekrar deneyin.',
                default => 'Trendyol API isteği başarısız oldu (HTTP '.$response->status().').',
            };

            throw new MarketplaceApiException($response->status(), $message);
        }

        if ($allowEmptyResponse && trim($response->body()) === '') {
            return [];
        }

        $data = $response->json();
        if (! is_array($data)) {
            throw new UnexpectedValueException('Trendyol API geçerli bir JSON yanıtı döndürmedi.');
        }

        return $data;
    }
}
