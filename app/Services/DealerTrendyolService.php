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
        $attrCache = [];
        // Form / kayıtlı fallback (zorunlu değil — XML markası öncelikli)
        $defaultBrand = $brandId && $brandId > 0
            ? $brandId
            : ($this->categoryMatcher->fallbackBrandId() ?: null);

        $readyCreate = [];
        $readyUpdate = [];
        $failed = 0;
        $errors = [];

        // Generic marka bir kez (ASTRALTECH gibi bilinmeyen isimler için)
        $genericBrandId = $defaultBrand ?: $this->api->resolveGenericBrandId($connection);

        // Benzersiz XML markalarını bir kez çöz
        $uniqueBrands = $products->pluck('brand')->map(fn ($b) => trim((string) $b))->filter()->unique()->values();
        foreach ($uniqueBrands as $bn) {
            $ck = mb_strtolower($bn);
            if (array_key_exists($ck, $brandCache)) {
                continue;
            }
            try {
                $found = Cache::remember(
                    'trendyol_brand_v2_'.md5($ck),
                    now()->addDays(14),
                    function () use ($connection, $bn, $genericBrandId) {
                        $id = $this->api->findBrandId($connection, $bn);
                        // Trendyol'da yoksa generic / varsayılan
                        return $id ?: $genericBrandId;
                    }
                );
                $brandCache[$ck] = $found;
            } catch (Throwable $e) {
                $brandCache[$ck] = $genericBrandId;
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

            // Marka: XML adı → Trendyol ID; yoksa Diğer / form varsayılan (asla bloklama)
            $productBrandId = null;
            $brandName = trim((string) ($product->brand ?? ''));
            if ($brandName !== '') {
                $cacheKey = mb_strtolower($brandName);
                if (! empty($brandCache[$cacheKey])) {
                    $productBrandId = (int) $brandCache[$cacheKey];
                }
            }
            if (! $productBrandId) {
                $productBrandId = $defaultBrand ?: $genericBrandId;
            }
            if (! $productBrandId) {
                // Son çare masaüstü BRAND_ID
                $productBrandId = 2613880;
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
                    $productAttrs = $attributes !== []
                        ? $attributes
                        : $this->attributesForCategory(
                            $connection,
                            (int) $productCategoryId,
                            $attrCache,
                            (string) ($product->title ?? ''),
                            (string) ($product->description ?? ''),
                            (string) ($product->category_path ?? $product->category ?? ''),
                        );
                    if ($productAttrs === null) {
                        $failed++;
                        $errors[] = ($product->stock_code ?: $product->id).': zorunlu kategori özellikleri doldurulamadı (kat: '.$productCategoryId.')';
                        continue;
                    }
                    $item = TrendyolProductPayload::item([
                        'barcode' => $barcode,
                        'stock_code' => $product->stock_code,
                        'title' => $this->titleFor($product, $variant),
                        'description' => $product->description,
                        'images' => (array) ($product->images ?? []),
                        'quantity' => $quantity,
                        'sale_price' => $sale,
                        'list_price' => $list,
                        'vat_rate' => $product->tax_rate ?: 20,
                        'desi' => $product->desi,
                        'category_id' => $productCategoryId,
                        'brand_id' => $productBrandId,
                        'attributes' => $productAttrs,
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

        // Yeni ürün oluştur + batch sonucunu doğrula (masaüstü gibi)
        foreach (array_chunk($readyCreate, self::CHUNK_SIZE) as $chunk) {
            $items = array_column($chunk, 'item');
            $listingIds = array_map(fn (array $row) => $row['listing']->id, $chunk);
            $barcodeToListing = [];
            foreach ($chunk as $row) {
                $bc = (string) ($row['item']['barcode'] ?? $row['listing']->barcode);
                $barcodeToListing[$bc] = $row['listing']->id;
            }

            try {
                $response = $this->api->createProducts($connection, $items);
                $batchId = (string) ($response['batchRequestId'] ?? '');

                DealerTrendyolListing::query()->whereIn('id', $listingIds)->update([
                    'status' => 'sent',
                    'batch_request_id' => $batchId !== '' ? $batchId : null,
                    'error' => null,
                    'sent_at' => now(),
                ]);

                if ($batchId !== '') {
                    $batches[] = $batchId;
                    // Batch sonucu gelene kadar birkaç kez dene (Trendyol genelde 2–15 sn)
                    $verify = $this->waitAndApplyBatchResult($dealer, $connection, $batchId, $barcodeToListing);
                    $sent += $verify['created'];
                    $failed += $verify['failed'];
                    $errors = array_merge($errors, $verify['errors']);
                    if ($verify['pending']) {
                        // Arka planda tekrar kontrol
                        \App\Jobs\VerifyTrendyolBatch::dispatch($dealer->id, $batchId)
                            ->delay(now()->addSeconds(20));
                    }
                } else {
                    // batchRequestId yoksa gerçekten kabul edilmemiş say
                    $failed += count($chunk);
                    $errors[] = 'Trendyol batchRequestId dönmedi — istek reddedilmiş olabilir.';
                    DealerTrendyolListing::query()->whereIn('id', $listingIds)->update([
                        'status' => 'failed',
                        'error' => 'batchRequestId yok',
                    ]);
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
    /**
     * status=sent olan tüm listing'lerin batch sonuçlarını yeniden sorgula.
     * @return array{batches: int, created: int, failed: int, pending: int, errors: list<string>}
     */
    public function recheckSentBatches(Dealer $dealer): array
    {
        $connection = $this->connection($dealer);
        $batchIds = DealerTrendyolListing::query()
            ->where('dealer_id', $dealer->id)
            ->where('status', 'sent')
            ->whereNotNull('batch_request_id')
            ->distinct()
            ->pluck('batch_request_id')
            ->filter()
            ->values()
            ->all();

        $created = 0;
        $failed = 0;
        $pending = 0;
        $errors = [];

        foreach ($batchIds as $batchId) {
            $listings = DealerTrendyolListing::query()
                ->where('dealer_id', $dealer->id)
                ->where('batch_request_id', $batchId)
                ->where('status', 'sent')
                ->get();
            $map = [];
            foreach ($listings as $l) {
                $map[(string) $l->barcode] = $l->id;
            }
            try {
                $v = $this->applyBatchResult($dealer, $connection, (string) $batchId, $map);
                $created += $v['created'];
                $failed += $v['failed'];
                $errors = array_merge($errors, $v['errors']);
            } catch (Throwable $e) {
                $pending++;
                if (count($errors) < 10) {
                    $errors[] = $batchId.': '.$e->getMessage();
                }
            }
        }

        return [
            'batches' => count($batchIds),
            'created' => $created,
            'failed' => $failed,
            'pending' => $pending,
            'errors' => array_slice(array_values(array_unique($errors)), 0, 20),
        ];
    }

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

            $st = strtoupper((string) (data_get($row, 'status') ?? data_get($row, 'itemStatus') ?? ''));
            if (in_array($st, ['SUCCESS', 'SUCCESSFUL', 'CREATED', 'APPROVED'], true)) {
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

    /**
     * Trendyol'dan ürün sil.
     * @param  'all'|int  $scope  all = tüm listingler, int = source_id
     * @return array{deleted: int, failed: int, errors: list<string>}
     */
    public function deleteFromTrendyol(Dealer $dealer, string|int $scope = 'all'): array
    {
        $connection = $this->connection($dealer);
        $query = DealerTrendyolListing::query()
            ->where('dealer_id', $dealer->id)
            ->whereIn('status', ['sent', 'created', 'pending', 'failed']);

        if ($scope !== 'all') {
            $sourceId = (int) $scope;
            $query->whereHas('product', fn ($q) => $q->where('source_id', $sourceId));
        }

        $deleted = 0;
        $failed = 0;
        $errors = [];

        $query->orderBy('id')->chunkById(200, function ($listings) use ($connection, $dealer, &$deleted, &$failed, &$errors): void {
            $items = [];
            $ids = [];
            foreach ($listings as $listing) {
                $bc = trim((string) $listing->barcode);
                if ($bc === '') {
                    continue;
                }
                $items[] = ['barcode' => $bc];
                $ids[] = $listing->id;
            }
            if ($items === []) {
                return;
            }
            try {
                $this->api->deleteProducts($connection, $items);
                DealerTrendyolListing::query()->whereIn('id', $ids)->delete();
                $deleted += count($ids);
            } catch (Throwable $e) {
                $failed += count($ids);
                $errors[] = $e->getMessage();
                $dealer->update(['trendyol_last_error' => mb_substr($e->getMessage(), 0, 1000)]);
            }
        });

        return ['deleted' => $deleted, 'failed' => $failed, 'errors' => array_values(array_unique($errors))];
    }

    /**
     * Masaüstü build_attributes: zorunlu kategori özelliklerini doldur.
     * @param  array<int, mixed>  $cache
     * @return list<array<string, mixed>>|null
     */
    /**
     * Zorunlu özellikleri ürün adından türet (renk, beden, menşei, garanti…).
     * @param  array<int, mixed>  $cache
     * @return list<array<string, mixed>>|null
     */
    private function attributesForCategory(
        MarketplaceConnection $connection,
        int $categoryId,
        array &$cache,
        string $title = '',
        string $description = '',
        string $categoryPath = '',
    ): ?array {
        if ($categoryId <= 0) {
            return [];
        }
        if (! array_key_exists($categoryId, $cache)) {
            try {
                $data = Cache::remember(
                    'trendyol_cat_attrs_'.$categoryId,
                    now()->addDays(3),
                    fn () => $this->api->categoryAttributes($connection, $categoryId)
                );
                $cache[$categoryId] = is_array($data['categoryAttributes'] ?? null) ? $data['categoryAttributes'] : [];
            } catch (Throwable $e) {
                $cache[$categoryId] = null;
            }
        }
        $rows = $cache[$categoryId];
        if ($rows === null) {
            return null;
        }
        if ($rows === []) {
            return [];
        }

        $blob = $this->normalizeText($title.' '.$description.' '.$categoryPath);
        $inferredColor = $this->inferColor($blob, $title);
        $inferredSize = $this->inferSize($blob, $title);
        $inferredGender = $this->inferGender($blob);
        $inferredAge = $this->inferAge($blob);

        $out = [];
        foreach ($rows as $a) {
            if (! is_array($a) || empty($a['required'])) {
                continue;
            }
            $attr = is_array($a['attribute'] ?? null) ? $a['attribute'] : [];
            $aid = (int) ($attr['id'] ?? 0);
            if ($aid <= 0) {
                continue;
            }
            $name = mb_strtolower((string) ($attr['name'] ?? ''));
            $vals = is_array($a['attributeValues'] ?? null) ? $a['attributeValues'] : [];
            $allow = ! empty($a['allowCustom']);

            $pref = [];
            $customFallback = 'Standart';

            if (str_contains($name, 'menşei') || str_contains($name, 'mensei') || str_contains($name, 'origin') || str_contains($name, 'üretim yeri')) {
                $pref = ['TR', 'Türkiye', 'Turkey', 'Turkiye'];
                $customFallback = 'TR';
            } elseif (str_contains($name, 'yaş') || str_contains($name, 'yas grub')) {
                $pref = $inferredAge;
                $customFallback = $inferredAge[0] ?? 'Yetişkin';
            } elseif (str_contains($name, 'cinsiyet')) {
                $pref = $inferredGender;
                $customFallback = $inferredGender[0] ?? 'Unisex';
            } elseif (str_contains($name, 'renk') || str_contains($name, 'color') || str_contains($name, 'web color') || str_contains($name, 'renk ailesi')) {
                $pref = $inferredColor;
                $customFallback = $inferredColor[0] ?? 'Siyah';
            } elseif (str_contains($name, 'beden') || str_contains($name, 'size') || str_contains($name, 'numara') || str_contains($name, 'ölçü')) {
                $pref = $inferredSize;
                $customFallback = $inferredSize[0] ?? 'Tek Ebat';
            } elseif (str_contains($name, 'garanti süresi') || str_contains($name, 'garanti suresi') || (str_contains($name, 'garanti') && str_contains($name, 'süre'))) {
                $pref = $this->inferWarrantyMonths($blob, $title);
                $customFallback = '2 Yıl';
            } elseif (str_contains($name, 'garanti tipi') || str_contains($name, 'garanti tür')) {
                $pref = ['Distribütör Garantili', 'İthalatçı Garantili', 'Üretici Garantili'];
                $customFallback = 'Distribütör Garantili';
            } elseif (str_contains($name, 'materyal') || str_contains($name, 'malzeme') || str_contains($name, 'kumaş')) {
                $pref = $this->inferMaterial($blob);
                $customFallback = $pref[0] ?? 'Diğer';
            }

            $chosen = $this->pickAttributeValue($vals, $pref);
            if ($chosen !== null) {
                $out[] = [
                    'attributeId' => $aid,
                    'attributeValueId' => (int) $chosen['id'],
                ];
            } elseif ($allow) {
                $out[] = [
                    'attributeId' => $aid,
                    'customAttributeValue' => mb_substr($customFallback, 0, 50),
                ];
            } elseif ($vals !== []) {
                // Son çare: listedeki ilk değer
                $first = $vals[0];
                if (is_array($first) && ! empty($first['id'])) {
                    $out[] = [
                        'attributeId' => $aid,
                        'attributeValueId' => (int) $first['id'],
                    ];
                }
            }
        }

        return $out;
    }

    /** @param  list<array<string,mixed>>  $vals @param  list<string>  $pref */
    private function pickAttributeValue(array $vals, array $pref): ?array
    {
        if ($vals === []) {
            return null;
        }
        $valMap = [];
        foreach ($vals as $v) {
            if (! is_array($v) || empty($v['id'])) {
                continue;
            }
            $vn = mb_strtolower(trim((string) ($v['name'] ?? '')));
            if ($vn !== '') {
                $valMap[$vn] = $v;
            }
        }
        foreach ($pref as $p) {
            $pl = mb_strtolower(trim($p));
            if ($pl === '') {
                continue;
            }
            if (isset($valMap[$pl])) {
                return $valMap[$pl];
            }
            // kısmi eşleşme
            foreach ($valMap as $vn => $v) {
                if (str_contains($vn, $pl) || str_contains($pl, $vn)) {
                    return $v;
                }
            }
        }

        return null;
    }

    private function normalizeText(string $s): string
    {
        $s = mb_strtolower($s);
        $map = ['ı' => 'i', 'İ' => 'i', 'ş' => 's', 'ğ' => 'g', 'ü' => 'u', 'ö' => 'o', 'ç' => 'c', 'â' => 'a', 'î' => 'i', 'û' => 'u'];
        $s = strtr($s, $map);

        return preg_replace('/\s+/', ' ', $s) ?? $s;
    }

    /** @return list<string> */
    private function inferColor(string $blob, string $title): array
    {
        $colors = [
            'siyah' => ['Siyah', 'Black'],
            'beyaz' => ['Beyaz', 'White'],
            'kirmizi' => ['Kırmızı', 'Kirmizi', 'Red'],
            'mavi' => ['Mavi', 'Lacivert', 'Blue'],
            'lacivert' => ['Lacivert', 'Mavi'],
            'yesil' => ['Yeşil', 'Yesil', 'Green'],
            'sari' => ['Sarı', 'Sari', 'Yellow'],
            'turuncu' => ['Turuncu', 'Orange'],
            'pembe' => ['Pembe', 'Pink'],
            'mor' => ['Mor', 'Purple'],
            'gri' => ['Gri', 'Gray', 'Grey'],
            'kahve' => ['Kahverengi', 'Kahve', 'Brown'],
            'bej' => ['Bej', 'Beige'],
            'altin' => ['Altın', 'Gold'],
            'gumus' => ['Gümüş', 'Gumus', 'Silver'],
            'seffaf' => ['Şeffaf', 'Seffaf', 'Transparent'],
            'cok renk' => ['Çok Renkli', 'Cok Renkli', 'Multicolor'],
            'renkli' => ['Çok Renkli', 'Cok Renkli'],
        ];
        $found = [];
        foreach ($colors as $needle => $prefs) {
            if (str_contains($blob, $needle)) {
                $found = array_merge($found, $prefs);
            }
        }
        if ($found !== []) {
            return array_values(array_unique($found));
        }

        return ['Siyah', 'Çok Renkli', 'Beyaz', 'Gri'];
    }

    /** @return list<string> */
    private function inferSize(string $blob, string $title): array
    {
        // Açık beden: S, M, L, XL, XXL, 36-46
        if (preg_match('/\b(xxxl|xxl|xl|xs)\b/iu', $title, $m)) {
            $t = mb_strtoupper($m[1]);

            return [$t, $m[1]];
        }
        if (preg_match('/\b([sml])\b/iu', $title, $m)) {
            $t = mb_strtoupper($m[1]);

            return [$t, $m[1]];
        }
        if (preg_match('/\b(3[6-9]|4[0-6])\b/', $title, $m)) {
            return [$m[1]];
        }
        if (preg_match('/(\d+(?:[.,]\d+)?)\s*(?:cm|mm|inch|in|metre|mt|m)\b/iu', $blob, $m)) {
            $v = str_replace(',', '.', $m[1]);

            return [$m[0], $v, 'Tek Ebat', 'Standart'];
        }
        // Set / kit / takım → tek ebat
        if (preg_match('/\b(set|takim|kit|paket|combo)\b/u', $blob)) {
            return ['Tek Ebat', 'Standart', 'Tek Beden', 'One Size'];
        }

        return ['Tek Ebat', 'Standart', 'Tek Beden', 'One Size', 'Tek Boy'];
    }

    /** @return list<string> */
    private function inferGender(string $blob): array
    {
        if (preg_match('/\b(kadin|kiz|bayan|women|female)\b/u', $blob)) {
            return ['Kadın / Kız', 'Kadın', 'Kız', 'Unisex'];
        }
        if (preg_match('/\b(erkek|bay|men|male|oğlan|oglan)\b/u', $blob)) {
            return ['Erkek', 'Unisex'];
        }
        if (preg_match('/\b(cocuk|çocuk|bebek|kids|child|baby)\b/u', $blob)) {
            return ['Unisex', 'Çocuk', 'Erkek Çocuk', 'Kız Çocuk'];
        }

        return ['Unisex', 'Kadın / Kız', 'Erkek'];
    }

    /** @return list<string> */
    private function inferAge(string $blob): array
    {
        if (preg_match('/\b(bebek|0-12|0-24 ay)\b/u', $blob)) {
            return ['Bebek', '0-24 Ay'];
        }
        if (preg_match('/\b(cocuk|çocuk|kids|genç|genc)\b/u', $blob)) {
            return ['Çocuk', 'Genç', 'Yetişkin'];
        }

        return ['Yetişkin', 'Yetiskin', 'Adult'];
    }

    /** @return list<string> */
    private function inferWarrantyMonths(string $blob, string $title): array
    {
        if (preg_match('/(\d+)\s*y[iı]l/ui', $title.' '.$blob, $m)) {
            $y = (int) $m[1];

            return [$y.' Yıl', ($y * 12).' Ay', (string) $y.' Yıl'];
        }
        if (preg_match('/(\d+)\s*ay/ui', $title.' '.$blob, $m)) {
            $a = (int) $m[1];

            return [$a.' Ay', (string) $a.' Ay'];
        }
        // Elektronik / alet → 2 yıl
        if (preg_match('/\b(matkap|tiras|tıraş|kulaklik|hoparlor|led|lamba|sarj|şarj|powerbank|gamepad)\b/u', $blob)) {
            return ['2 Yıl', '24 Ay', '1 Yıl', '12 Ay'];
        }

        return ['2 Yıl', '24 Ay', '1 Yıl', '12 Ay', '6 Ay'];
    }

    /** @return list<string> */
    private function inferMaterial(string $blob): array
    {
        $map = [
            'plastik' => ['Plastik'],
            'metal' => ['Metal', 'Çelik'],
            'celik' => ['Çelik', 'Metal'],
            'ahsap' => ['Ahşap', 'Ahsap'],
            'cam' => ['Cam'],
            'silikon' => ['Silikon'],
            'deri' => ['Deri'],
            'kumas' => ['Kumaş', 'Tekstil'],
            'pamuk' => ['Pamuk'],
            'abs' => ['Plastik', 'ABS'],
        ];
        foreach ($map as $needle => $prefs) {
            if (str_contains($blob, $needle)) {
                return $prefs;
            }
        }

        return ['Diğer', 'Plastik', 'Metal'];
    }

    /**
     * Batch sonucunu birkaç denemede oku.
     * @param  array<string, int>  $barcodeToListing
     * @return array{created: int, failed: int, errors: list<string>, pending: bool}
     */
    private function waitAndApplyBatchResult(
        Dealer $dealer,
        MarketplaceConnection $connection,
        string $batchId,
        array $barcodeToListing,
        int $attempts = 6,
        int $sleepMs = 2500,
    ): array {
        $lastError = '';
        for ($i = 0; $i < $attempts; $i++) {
            if ($i > 0) {
                usleep($sleepMs * 1000);
            } else {
                usleep(1200000); // ilk bekleme ~1.2s
            }
            try {
                $result = $this->applyBatchResult($dealer, $connection, $batchId, $barcodeToListing);
                $result['pending'] = false;

                return $result;
            } catch (Throwable $e) {
                $lastError = $e->getMessage();
            }
        }

        return [
            'created' => 0,
            'failed' => 0,
            'errors' => ['Batch '.$batchId.' henüz hazır değil: '.$lastError.' — arka planda tekrar kontrol edilecek.'],
            'pending' => true,
        ];
    }

    private function applyBatchResult(Dealer $dealer, MarketplaceConnection $connection, string $batchId, array $barcodeToListing): array
    {
        $result = $this->api->batchResult($connection, $batchId);
        $created = 0;
        $failed = 0;
        $errors = [];

        $items = $result['items'] ?? [];
        if (! is_array($items) || $items === []) {
            $status = (string) ($result['status'] ?? '');
            if (in_array(mb_strtoupper($status), ['COMPLETED', 'FINISHED', 'DONE'], true)) {
                $failCount = (int) ($result['failedItemCount'] ?? 0);
                $itemCount = (int) ($result['itemCount'] ?? 0);
                if ($itemCount > 0) {
                    return [
                        'created' => max(0, $itemCount - $failCount),
                        'failed' => $failCount,
                        'errors' => $failCount > 0 ? ['Batch failedItemCount='.$failCount] : [],
                    ];
                }
            }
            throw new \RuntimeException('Batch henüz hazır değil: '.$status);
        }

        foreach ($items as $row) {
            if (! is_array($row)) {
                continue;
            }
            $barcode = (string) (
                data_get($row, 'requestItem.barcode')
                ?? data_get($row, 'requestItem.products.0.barcode')
                ?? data_get($row, 'barcode')
                ?? ''
            );
            $status = mb_strtoupper((string) ($row['status'] ?? data_get($row, 'itemStatus') ?? ''));
            $failure = '';
            if (is_array($row['failureReasons'] ?? null)) {
                $failure = implode('; ', array_map(
                    fn ($r) => is_string($r) ? $r : json_encode($r, JSON_UNESCAPED_UNICODE),
                    $row['failureReasons']
                ));
            } else {
                $failure = (string) ($row['failureReason'] ?? $row['reason'] ?? data_get($row, 'statusDescription') ?? '');
            }

            $listingId = $barcodeToListing[$barcode] ?? null;
            // Trendyol bazen status=SUCCESS, bazen sadece failureReasons dolu gelir
            $ok = in_array($status, ['SUCCESS', 'SUCCESSFUL', 'CREATED', 'APPROVED'], true);
            if (! $ok && $status === '' && $failure === '') {
                $ok = true; // belirsiz ama hata yok
            }
            if ($failure !== '' && ! $ok) {
                $ok = false;
            }
            if (in_array($status, ['FAILED', 'FAIL', 'ERROR', 'REJECTED'], true)) {
                $ok = false;
            }

            if ($ok) {
                $created++;
                if ($listingId) {
                    DealerTrendyolListing::query()->whereKey($listingId)->update([
                        'status' => 'created',
                        'error' => null,
                        'checked_at' => now(),
                    ]);
                }
            } else {
                $failed++;
                $msg = $failure !== '' ? $failure : ($status !== '' ? $status : 'Trendyol reddetti');
                if ($listingId) {
                    DealerTrendyolListing::query()->whereKey($listingId)->update([
                        'status' => 'failed',
                        'error' => mb_substr($msg, 0, 1000),
                        'checked_at' => now(),
                    ]);
                }
                if (count($errors) < 15) {
                    $errors[] = ($barcode !== '' ? $barcode.': ' : '').mb_substr($msg, 0, 180);
                }
            }
        }

        return ['created' => $created, 'failed' => $failed, 'errors' => $errors];
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
