<?php

namespace App\Services;

use App\Models\Dealer;
use App\Models\DealerTrendyolListing;
use App\Models\MarketplaceConnection;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use LogicException;
use Throwable;

/**
 * Bayinin kendi Trendyol mağazasına ürün gönderme, sonuç sorgulama ve fiyat/stok eşitleme.
 */
class DealerTrendyolService
{
    public const CHUNK_SIZE = 100;

    public function __construct(
        private readonly TrendyolMarketplaceService $api,
        private readonly PricingService $pricing,
        private readonly TrendyolPriceCalculator $priceCalculator,
        private readonly TrendyolCategoryMatcher $categoryMatcher,
    ) {}

    /**
     * Veritabanına kaydedilmeyen, yalnızca istek için kullanılan bağlantı nesnesi.
     */
    public function connection(Dealer $dealer): MarketplaceConnection
    {
        if (! $dealer->hasTrendyolCredentials()) {
            throw new LogicException('Bu bayi için Trendyol mağaza numarası ve API bilgileri kaydedilmemiş.');
        }

        $credentials = $dealer->trendyol_credentials;

        return new MarketplaceConnection([
            'provider' => 'trendyol',
            'name' => $dealer->company_name,
            'account_id' => (string) $dealer->trendyol_seller_id,
            'credentials' => [
                'api_key' => $credentials['api_key'],
                'api_secret' => $credentials['api_secret'],
            ],
        ]);
    }

    public function testConnection(Dealer $dealer): void
    {
        $this->api->testConnection($this->connection($dealer));
    }

    /**
     * Seçilen ürünleri bayinin Trendyol mağazasına gönderir.
     *
     * @param  array<int, int>  $productIds
     * @param  array<int, array<string, mixed>>  $attributes  Trendyol kategori özellikleri
     * @return array{sent: int, failed: int, batches: array<int, string>, errors: array<int, string>}
     */
    public function send(Dealer $dealer, array $productIds, ?int $categoryId = null, ?int $brandId = null, array $attributes = []): array
    {
        $connection = $this->connection($dealer);
        $margin = $this->dealerMargin($dealer);

        $products = Product::query()
            ->with('variants')
            ->whereIn('id', $productIds)
            ->get();

        // Kategori ağacı yalnızca otomatik eşleme gerekiyorsa (manuel kategori yoksa)
        $leaves = [];
        if (! $categoryId) {
            try {
                $leaves = Cache::remember(
                    'trendyol_category_leaves',
                    now()->addHours(24),
                    fn () => $this->api->categoryLeaves($connection)
                );
            } catch (Throwable $e) {
                report($e);
            }
        }

        $brandCache = [];
        // Form / kayıtlı fallback (zorunlu değil — XML markası öncelikli)
        $defaultBrand = $brandId && $brandId > 0
            ? $brandId
            : ($this->categoryMatcher->fallbackBrandId() ?: null);

        $readyCreate = [];
        $readyUpdate = [];
        $failed = 0;
        $errors = [];

        // Benzersiz XML markalarını bir kez çöz (11k ürün → birkaç API çağrısı)
        $uniqueBrands = $products->pluck('brand')->map(fn ($b) => trim((string) $b))->filter()->unique()->values();
        foreach ($uniqueBrands as $bn) {
            $ck = mb_strtolower($bn);
            if (array_key_exists($ck, $brandCache)) {
                continue;
            }
            try {
                $brandCache[$ck] = Cache::remember(
                    'trendyol_brand_'.md5($ck),
                    now()->addDays(14),
                    fn () => $this->api->findBrandId($connection, $bn)
                );
            } catch (Throwable $e) {
                $brandCache[$ck] = null;
            }
        }

        foreach ($products as $product) {
            $resolved = $this->categoryMatcher->match($product, $leaves, $categoryId && $categoryId > 0 ? $categoryId : null);
            $productCategoryId = $resolved['id'] ?? null;

            if (! $productCategoryId) {
                $failed++;
                $errors[] = ($product->stock_code ?: $product->id).': kategori otomatik bulunamadı (başlık: '.mb_substr($product->title, 0, 40).')';
                continue;
            }

            // Marka önceliği: 1) XML markası → Trendyol API  2) form varsayılan  3) yoksa hata
            $productBrandId = null;
            $brandName = trim((string) ($product->brand ?? ''));
            if ($brandName !== '') {
                $cacheKey = mb_strtolower($brandName);
                if (! array_key_exists($cacheKey, $brandCache)) {
                    try {
                        $brandCache[$cacheKey] = Cache::remember(
                            'trendyol_brand_'.md5($cacheKey),
                            now()->addDays(14),
                            function () use ($connection, $brandName) {
                                $id = $this->api->findBrandId($connection, $brandName);
                                // Kısmi eşleşme bulunamazsa birebir "Diğer" deneme
                                if (! $id) {
                                    $id = $this->api->findBrandId($connection, 'Diğer')
                                        ?: $this->api->findBrandId($connection, 'Diger');
                                }

                                return $id;
                            }
                        );
                    } catch (Throwable $e) {
                        $brandCache[$cacheKey] = null;
                    }
                }
                if ($brandCache[$cacheKey]) {
                    $productBrandId = (int) $brandCache[$cacheKey];
                }
            }
            // XML'de marka yok / API bulamadı → form varsayılanı
            if (! $productBrandId && $defaultBrand) {
                $productBrandId = $defaultBrand;
            }
            if (! $productBrandId) {
                $failed++;
                $errors[] = ($product->stock_code ?: $product->id).': marka yok (XML: "'.($brandName ?: 'boş').'"). XML markasını doldurun veya varsayılan Marka No girin.';
                continue;
            }

            foreach ($this->entries($product) as $variant) {
                $barcode = $this->barcodeFor($product, $variant);
                $sale = $this->salePrice($product, $variant, $margin);
                $list = max((float) ($product->list_price ?? 0), $sale);
                $quantity = $this->quantityFor($product, $variant);

                $existing = DealerTrendyolListing::query()
                    ->where('dealer_id', $dealer->id)
                    ->where('barcode', $barcode)
                    ->first();

                $alreadyOnTy = $existing && in_array($existing->status, ['sent', 'created'], true);

                $listing = DealerTrendyolListing::query()->updateOrCreate(
                    ['dealer_id' => $dealer->id, 'barcode' => $barcode],
                    [
                        'product_id' => $product->id,
                        'product_variant_id' => $variant?->id,
                        'sale_price' => $sale,
                        'list_price' => $list,
                        'quantity' => $quantity,
                        'category_id' => $productCategoryId,
                        'brand_id' => $productBrandId,
                        'status' => $alreadyOnTy ? $existing->status : 'pending',
                        'error' => null,
                    ],
                );

                // Mevcut Trendyol ürünü → sadece fiyat/stok güncelle (masaüstü mantığı)
                if ($alreadyOnTy) {
                    $readyUpdate[] = [
                        'listing' => $listing,
                        'item' => [
                            'barcode' => $barcode,
                            'quantity' => $quantity,
                            'salePrice' => $sale,
                            'listPrice' => $list,
                        ],
                    ];
                    continue;
                }

                try {
                    $item = TrendyolProductPayload::item([
                        'barcode' => $barcode,
                        'stock_code' => $product->stock_code,
                        'title' => $this->titleFor($product, $variant),
                        'description' => $product->description,
                        'images' => (array) ($product->images ?? []),
                        'quantity' => $quantity,
                        'sale_price' => $sale,
                        'list_price' => $list,
                        'vat_rate' => $product->tax_rate,
                        'desi' => $product->desi,
                        'category_id' => $productCategoryId,
                        'brand_id' => $productBrandId,
                        'attributes' => $attributes,
                    ]);
                } catch (InvalidArgumentException $e) {
                    $listing->update(['status' => 'failed', 'error' => $e->getMessage()]);
                    $failed++;
                    $errors[] = $product->stock_code.': '.$e->getMessage();

                    continue;
                }

                $readyCreate[] = ['listing' => $listing, 'item' => $item];
            }
        }

        $sent = 0;
        $batches = [];

        // Yeni ürün oluştur
        foreach (array_chunk($readyCreate, self::CHUNK_SIZE) as $chunk) {
            $items = array_column($chunk, 'item');
            $listingIds = array_map(fn (array $row) => $row['listing']->id, $chunk);

            try {
                $response = $this->api->createProducts($connection, $items);
                $batchId = (string) ($response['batchRequestId'] ?? '');

                DealerTrendyolListing::query()->whereIn('id', $listingIds)->update([
                    'status' => 'sent',
                    'batch_request_id' => $batchId !== '' ? $batchId : null,
                    'error' => null,
                    'sent_at' => now(),
                ]);

                $sent += count($chunk);
                if ($batchId !== '') {
                    $batches[] = $batchId;
                }
            } catch (Throwable $e) {
                DealerTrendyolListing::query()->whereIn('id', $listingIds)->update([
                    'status' => 'failed',
                    'error' => mb_substr($e->getMessage(), 0, 1000),
                ]);
                $failed += count($chunk);
                $errors[] = $e->getMessage();
                $dealer->update(['trendyol_last_error' => mb_substr($e->getMessage(), 0, 1000)]);
            }
        }

        // Mevcut ürün fiyat/stok güncelle
        foreach (array_chunk($readyUpdate, 1000) as $chunk) {
            $items = array_column($chunk, 'item');
            try {
                $response = $this->api->updatePriceAndInventory($connection, $items);
                $batchId = (string) ($response['batchRequestId'] ?? '');
                $sent += count($chunk);
                if ($batchId !== '') {
                    $batches[] = $batchId;
                }
            } catch (Throwable $e) {
                $failed += count($chunk);
                $errors[] = 'Fiyat/stok: '.$e->getMessage();
            }
        }

        return ['sent' => $sent, 'failed' => $failed, 'batches' => $batches, 'errors' => array_values(array_unique($errors))];
    }

    /**
     * Trendyol'un toplu işlem sonucunu okuyup ürün durumlarını günceller.
     *
     * @return array{status: string, created: int, failed: int}
     */
    public function checkBatch(Dealer $dealer, string $batchRequestId): array
    {
        $result = $this->api->batchResult($this->connection($dealer), $batchRequestId);

        $created = 0;
        $failed = 0;

        foreach ((array) ($result['items'] ?? []) as $row) {
            $barcode = data_get($row, 'requestItem.barcode') ?? data_get($row, 'requestItem.products.0.barcode');
            if (! is_string($barcode) || $barcode === '') {
                continue;
            }

            $listing = DealerTrendyolListing::query()
                ->where('dealer_id', $dealer->id)
                ->where('batch_request_id', $batchRequestId)
                ->where('barcode', $barcode)
                ->first();
            if ($listing === null) {
                continue;
            }

            if (strtoupper((string) data_get($row, 'status')) === 'SUCCESS') {
                $listing->update(['status' => 'created', 'error' => null, 'checked_at' => now()]);
                $created++;
            } else {
                $reasons = collect((array) data_get($row, 'failureReasons', []))
                    ->map(fn ($reason) => is_string($reason) ? $reason : json_encode($reason, JSON_UNESCAPED_UNICODE))
                    ->implode(' | ');
                $listing->update([
                    'status' => 'failed',
                    'error' => mb_substr($reasons !== '' ? $reasons : 'Trendyol ürünü reddetti.', 0, 1000),
                    'checked_at' => now(),
                ]);
                $failed++;
            }
        }

        return [
            'status' => (string) ($result['status'] ?? 'BİLİNMİYOR'),
            'created' => $created,
            'failed' => $failed,
        ];
    }

    /**
     * Trendyol'da oluşmuş ürünlerin fiyat ve stoğunu güncel XML verisiyle eşitler.
     *
     * @return array{updated: int, batches: array<int, string>}
     */
    public function syncInventory(Dealer $dealer): array
    {
        $connection = $this->connection($dealer);
        $margin = $this->dealerMargin($dealer);
        $items = [];

        DealerTrendyolListing::query()
            ->with(['product.variants', 'variant'])
            ->where('dealer_id', $dealer->id)
            ->whereIn('status', ['sent', 'created'])
            ->chunkById(200, function ($listings) use ($margin, &$items) {
                foreach ($listings as $listing) {
                    $product = $listing->product;
                    if ($product === null) {
                        continue;
                    }
                    $variant = $listing->variant;
                    $sale = $this->salePrice($product, $variant, $margin);

                    $items[] = [
                        'barcode' => $listing->barcode,
                        'quantity' => $this->quantityFor($product, $variant),
                        'salePrice' => $sale,
                        'listPrice' => max((float) $product->list_price, $sale),
                    ];

                    $listing->update([
                        'sale_price' => $sale,
                        'list_price' => max((float) $product->list_price, $sale),
                        'quantity' => $this->quantityFor($product, $variant),
                    ]);
                }
            });

        $batches = [];
        foreach (array_chunk($items, 1000) as $chunk) {
            $response = $this->api->updatePriceAndInventory($connection, $chunk);
            if (! empty($response['batchRequestId'])) {
                $batches[] = (string) $response['batchRequestId'];
            }
        }

        return ['updated' => count($items), 'batches' => $batches];
    }

    /** Bayinin kâr yüzdesi; tanımlı değilse platform varsayılanı. */
    public function dealerMargin(Dealer $dealer): float
    {
        return $dealer->default_marketplace_margin !== null
            ? (float) $dealer->default_marketplace_margin
            : $this->pricing->defaultMarketplaceMargin();
    }

    /**
     * Masaüstü pricing_engine ile aynı formül:
     * cost = bayiye satış fiyatı (dealer cost)
     * S = (landed + kar) / (1 - komisyon), kargo desi+fiyata göre, xx.99 yuvarlama
     * $margin yüzde olarak gelir (örn. 60).
     */
    public function salePrice(Product $product, ?ProductVariant $variant, float $margin): float
    {
        $base = (float) ($product->sell_price ?: $product->price ?: $product->cost_price ?: 0);
        $base += (float) ($variant?->price_diff ?? 0);
        if ($base <= 0) {
            return 0.0;
        }

        $desi = (float) ($product->desi ?: 5);
        if ($desi <= 0) {
            $desi = 5.0;
        }

        // margin yüzde (60) -> calculator 0.60 veya 60 kabul eder
        $calculated = $this->priceCalculator->calculate($base, $desi, [
            'profit_margin' => $margin,
            'commission_rate' => 0.15,
            'min_profit' => 10,
            'round_to' => 0.99,
            'delivery_type' => 'standart',
        ]);

        if ($calculated === null || $calculated <= 0) {
            // Fallback: basit marj
            return $this->pricing->calculateDealerRetailPrice($base, $margin);
        }

        return $calculated;
    }

    private function quantityFor(Product $product, ?ProductVariant $variant): int
    {
        if (! $product->is_active) {
            return 0;
        }

        return max(0, (int) ($variant?->stock ?? $product->stock));
    }

    private function barcodeFor(Product $product, ?ProductVariant $variant): string
    {
        if ($variant === null) {
            return TrendyolProductPayload::cleanBarcode((string) ($product->barcode ?: $product->stock_code));
        }

        $own = TrendyolProductPayload::cleanBarcode((string) $variant->barcode);

        return $own !== '' ? $own : TrendyolProductPayload::cleanBarcode($product->stock_code.'-'.($variant->sku ?: $variant->id));
    }

    private function titleFor(Product $product, ?ProductVariant $variant): string
    {
        return $variant === null ? $product->title : trim($product->title.' '.$variant->value);
    }

    /** @return array<int, ProductVariant|null> */
    private function entries(Product $product): array
    {
        if ($product->has_variants && $product->variants->isNotEmpty()) {
            return $product->variants->all();
        }

        return [null];
    }
}
