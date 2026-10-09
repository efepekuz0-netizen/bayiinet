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

    /**
     * Trendyol ürün silme — barcode listesi (max ~1000/istek).
     * @param  list<array{barcode: string}>  $items
     */
    public function deleteProducts(MarketplaceConnection $connection, array $items): array
    {
        return $this->send(
            $connection,
            'DELETE',
            $this->productPath($connection).'/products',
            ['items' => array_values($items)],
            allowEmptyResponse: true,
        );
    }


    /**
     * Kategori zorunlu/opsiyonel özellikleri.
     * @return array{categoryAttributes?: list<array<string,mixed>>}
     */
    public function categoryAttributes(MarketplaceConnection $connection, int $categoryId): array
    {
        return $this->get(
            $connection,
            '/integration/product/product-categories/'.$categoryId.'/attributes',
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


    /**
     * Trendyol kategori ağacı (yaprak kategoriler).
     * @return list<array{id:int,name:string,path:string}>
     */
    public function categoryLeaves(MarketplaceConnection $connection): array
    {
        $response = $this->get($connection, '/integration/product/product-categories');
        $roots = $response['categories'] ?? $response;
        if (! is_array($roots)) {
            return [];
        }

        $leaves = [];
        $walk = function ($nodes, array $path) use (&$walk, &$leaves): void {
            if (! is_array($nodes)) {
                return;
            }
            foreach ($nodes as $node) {
                if (! is_array($node)) {
                    continue;
                }
                $name = (string) ($node['name'] ?? '');
                $id = (int) ($node['id'] ?? 0);
                $sub = $node['subCategories'] ?? [];
                $next = $name !== '' ? array_merge($path, [$name]) : $path;
                if ((! is_array($sub) || $sub === []) && $id > 0 && $name !== '') {
                    $leaves[] = [
                        'id' => $id,
                        'name' => $name,
                        'path' => implode(' >>> ', $next),
                    ];
                } else {
                    $walk($sub, $next);
                }
            }
        };
        $walk($roots, []);

        return $leaves;
    }

    /**
     * Marka adına göre Trendyol brandId arar.
     */
    public function findBrandId(MarketplaceConnection $connection, string $brandName): ?int
    {
        $brandName = trim($brandName);
        if ($brandName === '') {
            return null;
        }

        $response = $this->get($connection, '/integration/product/brands', [
            'name' => $brandName,
            'page' => 0,
            'size' => 20,
        ]);

        $brands = $response['brands'] ?? $response['content'] ?? $response;
        if (! is_array($brands)) {
            return null;
        }

        $lower = mb_strtolower($brandName);
        foreach ($brands as $brand) {
            if (! is_array($brand)) {
                continue;
            }
            $name = mb_strtolower((string) ($brand['name'] ?? ''));
            if ($name === $lower && ! empty($brand['id'])) {
                return (int) $brand['id'];
            }
        }
        foreach ($brands as $brand) {
            if (! is_array($brand)) {
                continue;
            }
            $name = mb_strtolower((string) ($brand['name'] ?? ''));
            if ($name !== '' && (str_contains($name, $lower) || str_contains($lower, $name)) && ! empty($brand['id'])) {
                return (int) $brand['id'];
            }
        }

        $first = $brands[0] ?? null;
        if (is_array($first) && ! empty($first['id'])) {
            return (int) $first['id'];
        }

        return null;
    }

    /** Trendyol'da bilinmeyen markalar için "Diğer" veya masaüstü varsayılanı */
    public function resolveGenericBrandId(MarketplaceConnection $connection): int
    {
        return (int) \Illuminate\Support\Facades\Cache::remember(
            'trendyol_brand_generic_diger_v2',
            now()->addDays(30),
            function () use ($connection) {
                foreach (['Diğer', 'Diger', 'Other'] as $name) {
                    try {
                        $id = $this->findBrandId($connection, $name);
                        if ($id) {
                            return $id;
                        }
                    } catch (\Throwable) {
                    }
                }

                // Yapılandırılabilir yedek marka (varsayılan: masaüstü BRAND_ID)
                return (int) config('bayiinet.trendyol.fallback_brand_id', 2613880);
            }
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
            ->connectTimeout(8)
            ->timeout(30)
            ->withoutRedirecting()
            // Yalnızca ağ/bağlantı hatalarında yeniden dener (429 ve 5xx için
            // çağıran taraf kendi bekleme stratejisini uygular).
            ->retry(
                max(1, (int) config('bayiinet.trendyol.retry_times', 3)),
                max(200, (int) config('bayiinet.trendyol.retry_sleep_ms', 1500)),
                fn (\Throwable $exception): bool => $exception instanceof \Illuminate\Http\Client\ConnectionException,
            );

        $response = match ($method) {
            'GET' => $request->get(self::BASE_URL.$path, $data),
            'POST' => $request->post(self::BASE_URL.$path, $data),
            'PUT' => $request->put(self::BASE_URL.$path, $data),
            // Not: withBody() + delete() birlikte kullanıldığında Laravel gövdeyi
            // göndermiyordu (bodyFormat 'body' iken veri yok sayılıyor). Bu yüzden
            // gövde doğrudan delete() ikinci parametresiyle geçilir.
            'DELETE' => $request->delete(self::BASE_URL.$path, $data),
            default => throw new LogicException('Desteklenmeyen Trendyol API isteği.'),
        };

        return $this->decode($response, $allowEmptyResponse);
    }

    private function decode(Response $response, bool $allowEmptyResponse): array
    {
        if (! $response->successful()) {
            $body = $response->json();
            $detail = '';
            if (is_array($body)) {
                $detail = (string) ($body['message'] ?? $body['error'] ?? $body['errors'][0]['message'] ?? '');
                if ($detail === '' && isset($body['errors']) && is_array($body['errors'])) {
                    $detail = json_encode($body['errors'], JSON_UNESCAPED_UNICODE);
                }
            }
            if ($detail === '') {
                $detail = mb_substr(trim($response->body()), 0, 300);
            }

            $message = match ($response->status()) {
                401, 403 => 'Trendyol kimlik doğrulaması başarısız. Mağaza numarası ve API bilgilerini kontrol edin.',
                429 => 'Trendyol istek sınırına ulaşıldı. Biraz bekleyip tekrar deneyin.',
                default => 'Trendyol API hatası (HTTP '.$response->status().')'.($detail !== '' ? ': '.$detail : ''),
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
