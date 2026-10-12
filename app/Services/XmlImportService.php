<?php

namespace App\Services;

use App\Services\PricingService;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Source;
use App\Models\XmlImport;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class XmlImportService
{
    protected array $stats = [
        'total' => 0,
        'created' => 0,
        'updated' => 0,
        'skipped' => 0,
        'errors' => 0,
    ];

    protected array $errorList = [];

    /** Aynı anda veritabanına yazılan ürün grubu (bellek/performans dengesi). */
    protected array $batch = [];

    protected const BATCH_SIZE = 250;

    public function importFromFile(Source $source, string $filePath, ?int $userId = null): XmlImport
    {
        $this->resetStats();

        $import = XmlImport::create([
            'source_id' => $source->id,
            'user_id' => $userId,
            'file_name' => basename($filePath),
            'file_path' => $filePath,
            'status' => 'processing',
            'started_at' => now(),
        ]);

        return $this->processFile($import, $source, $filePath);
    }

    public function importFromUrl(Source $source, ?int $userId = null): XmlImport
    {
        $this->resetStats();

        $fileName = basename(parse_url((string) $source->url, PHP_URL_PATH) ?: 'feed.xml');
        $relativePath = "xml/{$source->id}/remote.xml";
        $filePath = Storage::disk('local')->path($relativePath);
        $import = XmlImport::create([
            'source_id' => $source->id,
            'user_id' => $userId,
            'file_name' => $fileName,
            'file_path' => $filePath,
            'status' => 'processing',
            'started_at' => now(),
        ]);

        try {
            if (! $this->isPublicHttpUrl($source->url)) {
                throw new \RuntimeException('XML bağlantısı geçerli bir herkese açık HTTP(S) adresi olmalıdır.');
            }

            $directory = dirname($filePath);
            if (! is_dir($directory) && ! @mkdir($directory, 0775, true) && ! is_dir($directory)) {
                throw new \RuntimeException('XML için depolama klasörü oluşturulamadı.');
            }

            $tempPath = $filePath.'.tmp';
            @unlink($tempPath);

            // Bellek dostu indirme: yanıt gövdesi RAM'de tutulmaz, doğrudan
            // diske akar (200 MB'lık feed `$response->body()` ile bellek
            // sınırını aşıyordu). Yönlendirmeler sınırlı sayıda izlenir;
            // tedarikçi feed'leri sık sık https'e yönlendirir.
            $response = Http::accept('application/xml, text/xml, */*')
                ->connectTimeout(10)
                ->timeout((int) config('bayiinet.xml.download_timeout', 600))
                ->withOptions([
                    'sink' => $tempPath,
                    'allow_redirects' => [
                        'max' => 5,
                        'strict' => true,
                        'referer' => false,
                        'protocols' => ['http', 'https'],
                    ],
                ])
                ->get($source->url);

            if (! $response->successful()) {
                @unlink($tempPath);
                throw new \RuntimeException("XML adresi HTTP {$response->status()} yanıtı verdi.");
            }

            $size = is_file($tempPath) ? (int) filesize($tempPath) : 0;
            if ($size <= 0) {
                @unlink($tempPath);
                throw new \RuntimeException('XML yanıtı boş geldi (0 bayt).');
            }

            if ($size > (int) config('bayiinet.xml.max_bytes', 200 * 1024 * 1024)) {
                @unlink($tempPath);
                throw new \RuntimeException('XML dosyası boyut sınırını aşıyor.');
            }

            if (! @rename($tempPath, $filePath)) {
                if (! @copy($tempPath, $filePath)) {
                    @unlink($tempPath);
                    throw new \RuntimeException('XML yanıtı güvenli depolama alanına kaydedilemedi.');
                }
                @unlink($tempPath);
            }

            $source->update(['file_path' => $relativePath]);
        } catch (\Throwable $e) {
            Log::error('XML Import Error: '.$e->getMessage());
            $import->update([
                'status' => 'failed',
                'log' => $e->getMessage(),
                'finished_at' => now(),
            ]);
            $source->update(['last_error' => $e->getMessage()]);

            return $import->fresh();
        }

        return $this->processFile($import, $source, $filePath);
    }

    protected function processFile(XmlImport $import, Source $source, string $filePath): XmlImport
    {
        try {
            $size = is_file($filePath) ? (int) filesize($filePath) : 0;
            $threshold = (int) config('bayiinet.xml.stream_threshold_bytes', 8 * 1024 * 1024);
            $handled = false;

            if ($size >= $threshold && class_exists(\XMLReader::class)) {
                // Büyük dosya: SimpleXML tüm ağacı belleğe alır (100 MB XML ≈
                // 600 MB+). XMLReader ile ürün ürün okuyup yalnızca bir ürünü
                // bellekte tutuyoruz.
                $found = $this->processStream($filePath, $source);
                $written = $this->stats['created'] + $this->stats['updated'];
                $handled = $found > 0 && $written > 0;

                if (! $handled) {
                    // Akış okuması ürünleri tanıyamadı. Hiçbir kayıt yazılmadığı
                    // için kanıtlanmış SimpleXML yoluna dönmek güvenli.
                    $this->resetStats();
                    Log::warning('XML akış okuması sonuç vermedi, SimpleXML denenecek', [
                        'source_id' => $source->id,
                        'size' => $size,
                        'found' => $found,
                    ]);
                }
            }

            if (! $handled) {
                $xml = simplexml_load_file($filePath, \SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
                if ($xml === false) {
                    throw new \RuntimeException('XML dosyası okunamadı veya geçersiz.');
                }

                $this->processXml($xml, $source);
            }

            $import->update([
                'status' => 'completed',
                'total_products' => $this->stats['total'],
                'created_count' => $this->stats['created'],
                'updated_count' => $this->stats['updated'],
                'skipped_count' => $this->stats['skipped'],
                'error_count' => $this->stats['errors'],
                'errors' => $this->errorList,
                'finished_at' => now(),
            ]);

            $source->update([
                'last_imported_at' => now(),
                'last_product_count' => $source->products()->count(),
                'last_error' => null,
            ]);

            Log::info('XML import completed', [
                'source_id' => $source->id,
                'total' => $this->stats['total'],
                'created' => $this->stats['created'],
                'updated' => $this->stats['updated'],
                'skipped' => $this->stats['skipped'],
                'errors' => $this->stats['errors'],
            ]);

            Cache::forget('xml_feed_catalog');
            Cache::forget('home_main_categories_v2');
            Cache::forget('admin_dash_stats_v2');
        } catch (\Throwable $e) {
            Log::error('XML Import Error: '.$e->getMessage());
            $import->update([
                'status' => 'failed',
                'log' => $e->getMessage(),
                'finished_at' => now(),
            ]);
            $source->update(['last_error' => $e->getMessage()]);
        }

        return $import->fresh();
    }

    protected function resetStats(): void
    {
        $this->stats = [
            'total' => 0,
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => 0,
        ];
        $this->errorList = [];
        $this->batch = [];
    }

    protected function isPublicHttpUrl(?string $url): bool
    {
        $parts = $url === null ? false : parse_url($url);
        if (
            $parts === false
            || ! isset($parts['scheme'], $parts['host'])
            || ! in_array(strtolower($parts['scheme']), ['http', 'https'], true)
            || isset($parts['user'])
            || isset($parts['pass'])
        ) {
            return false;
        }

        $host = trim($parts['host'], '[]');
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
        }

        if (preg_match('/(?:^|\.)localhost$/i', $host) === 1 || preg_match('/\.(?:local|internal|test)$/i', $host) === 1) {
            return false;
        }

        $records = @dns_get_record($host, DNS_A | DNS_AAAA);
        // DNS çözülemezse engelleme — birçok CDN / dinamik hostta kayıt boş dönebilir
        if ($records === false || $records === []) {
            return true;
        }

        foreach ($records as $record) {
            $address = $record['ip'] ?? $record['ipv6'] ?? null;
            if ($address !== null && filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return false;
            }
        }

        return true;
    }

    protected function processXml(\SimpleXMLElement $xml, Source $source): void
    {
        $products = $this->findProductNodes($xml);

        if (count($products) === 0) {
            throw new \RuntimeException('XML içinde ürün listesi bulunamadı. Kök düğüm altında product/Product/Urun/item arandı.');
        }

        foreach ($products as $item) {
            $this->importNode($item, $source);
        }

        $this->flushBatch($source);
    }

    /**
     * Bellek dostu içe aktarma: XMLReader ile ürün düğümleri teker teker
     * okunur, her düğüm işlendikten sonra bellekten düşer.
     *
     * @return int  bulunan ürün düğümü sayısı (0 ise çağıran SimpleXML'e döner)
     */
    protected function processStream(string $filePath, Source $source): int
    {
        $reader = new \XMLReader();

        if (! @$reader->open($filePath, null, LIBXML_NONET | LIBXML_COMPACT)) {
            throw new \RuntimeException('XML dosyası akış olarak açılamadı.');
        }

        $previousErrors = libxml_use_internal_errors(true);
        $found = 0;

        try {
            while (@$reader->read()) {
                if ($reader->nodeType !== \XMLReader::ELEMENT) {
                    continue;
                }

                $name = mb_strtolower((string) $reader->localName);
                if (! in_array($name, ['product', 'urun', 'item'], true)) {
                    continue;
                }

                $found++;

                $node = $reader->expand();

                // Not: XMLReader::expand() ile dönen düğümün ilişkili belgesi
                // olmadığı için doğrudan simplexml_import_dom() şu hatayı verir:
                //   "Imported Node must have associated Document"
                // Bu yüzden düğüm bir DOMDocument'e aktarılır.
                if ($node instanceof \DOMNode) {
                    $document = new \DOMDocument();
                    $imported = $document->importNode($node, true);

                    if ($imported !== false) {
                        $document->appendChild($imported);

                        $simple = simplexml_import_dom($imported, \SimpleXMLElement::class);
                        if ($simple instanceof \SimpleXMLElement) {
                            $this->importNode($simple, $source);
                        }
                    }
                }

                // İç içe aynı adlı düğümler tekrar işlenmesin
                $reader->next();
            }
        } finally {
            $reader->close();
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrors);
        }

        $this->flushBatch($source);

        return $found;
    }

    /** Tek bir ürün düğümünü işler ve grup dolunca toplu yazımı tetikler. */
    protected function importNode(\SimpleXMLElement $item, Source $source): void
    {
        $this->stats['total']++;

        try {
            $product = $this->parseProduct($item, $source);
        } catch (\Throwable $e) {
            $this->stats['errors']++;
            $this->errorList[] = [
                'stock_code' => $this->xmlText($item, ['stock_code', 'stockCode', 'ProductCode', 'StokKodu', 'code', 'sku'], 'unknown'),
                'error' => $e->getMessage(),
            ];

            return;
        }

        if ($product === null) {
            return;
        }

        $this->batch[$product['data']['stock_code']] = $product;

        if (count($this->batch) >= self::BATCH_SIZE) {
            $this->flushBatch($source);
        }
    }

    /** Bellekteki ürün grubunu veritabanına yazar. */
    protected function flushBatch(Source $source): void
    {
        if ($this->batch === []) {
            return;
        }

        $batch = $this->batch;
        $this->batch = [];

        $this->upsertProductBatch($batch, $source);
    }

    /**
     * Farklı tedarikçi XML şemalarını destekler.
     *
     * @return list<\SimpleXMLElement>
     */
    protected function findProductNodes(\SimpleXMLElement $xml): array
    {
        $candidates = [];

        $tryLists = [
            $xml->product ?? null,
            $xml->Product ?? null,
            $xml->products->product ?? null,
            $xml->products->Product ?? null,
            $xml->Products->Product ?? null,
            $xml->Products->product ?? null,
            $xml->Urunler->Urun ?? null,
            $xml->urunler->urun ?? null,
            $xml->Urun ?? null,
            $xml->urun ?? null,
            $xml->item ?? null,
            $xml->Item ?? null,
            $xml->items->item ?? null,
            $xml->Items->Item ?? null,
        ];

        foreach ($tryLists as $list) {
            if ($list === null) {
                continue;
            }
            if ($list instanceof \SimpleXMLElement && count($list) > 0) {
                foreach ($list as $node) {
                    $candidates[] = $node;
                }
                if ($candidates !== []) {
                    return $candidates;
                }
            }
        }

        // XPath yedek: herhangi bir product/Product/Urun/item düğümü
        foreach (['//product', '//Product', '//Urun', '//urun', '//item', '//Item'] as $xpath) {
            $found = $xml->xpath($xpath) ?: [];
            if (count($found) > 0) {
                return array_values($found);
            }
        }

        return [];
    }

    protected function parseProduct(\SimpleXMLElement $item, Source $source): ?array
    {
        $stockCode = $this->xmlText($item, ['stock_code', 'stockCode', 'ProductCode', 'StokKodu', 'stok_kodu', 'code', 'sku', 'SKU', 'StockCode', 'productCode', 'ProductId', 'id']);
        if (empty($stockCode)) {
            $this->stats['skipped']++;

            return null;
        }

        $variantData = [];
        if (isset($item->variants->variant)) {
            foreach ($item->variants->variant as $variant) {
                $variantPrice = $this->xmlText($variant, ['price', 'Price', 'Fiyat', 'fiyat', 'sale_price', 'salePrice']);

                $variantData[] = [
                    'barcode' => $this->xmlText($variant, ['barcode', 'Barkod']),
                    'sku' => $this->xmlText($variant, ['sku', 'SKU', 'stock_code', 'StokKodu', 'code']),
                    'name' => $this->xmlText($variant, ['name', 'VaryantAdi'], 'Seçenek'),
                    'value' => $this->xmlText($variant, ['value', 'Deger']),
                    'color' => $this->xmlText($variant, ['color', 'Renk']),
                    'stock' => (int) $this->xmlText($variant, ['stock', 'Quantity', 'Stok', 'quantity'], '0'),
                    'variant_price' => $variantPrice !== ''
                        ? (float) str_replace(',', '.', $variantPrice)
                        : null,
                    'price_diff' => (float) str_replace(
                        ',',
                        '.',
                        $this->xmlText($variant, ['price_diff', 'PriceDiff', 'fiyat_farki'], '0')
                    ),
                ];
            }
        }

        $stock = (int) $this->xmlText($item, ['stock', 'Quantity', 'Stok', 'stok', 'quantity', 'qty', 'Qty', 'Stock'], '0');
        $data = [
            'source_id' => $source->id,
            'stock_code' => $stockCode,
            'barcode' => $this->xmlText($item, ['barcode', 'Barcode', 'barcod', 'Barkod', 'barkod', 'ean', 'EAN']),
            'title' => $this->xmlText($item, ['title', 'name', 'ProductName', 'UrunAdi', 'urun_adi', 'Name', 'baslik', 'Title']),
            'brand' => $this->xmlText($item, ['brand', 'Brand', 'Marka', 'marka', 'manufacturer', 'Manufacturer']),
            'description' => $this->xmlText($item, ['description', 'Description', 'Aciklama', 'aciklama', 'Detail', 'detail']),
            'main_category' => $this->xmlText($item, ['main_category']),
            'sub_category' => $this->xmlText($item, ['sub_category']),
            'category_path' => $this->xmlText($item, ['category', 'Category']),
            'price' => (float) str_replace(',', '.', $this->xmlText($item, ['sale_price', 'price', 'Price', 'Fiyat', 'fiyat', 'bayi_fiyat', 'BayiFiyat', 'alis_fiyat', 'AlisFiyat'], '0')),
            'cost_price' => (float) str_replace(',', '.', $this->xmlText($item, ['cost_price', 'sale_price', 'price', 'Price', 'Fiyat', 'fiyat', 'bayi_fiyat', 'BayiFiyat', 'alis_fiyat'], '0')),
            'list_price' => $this->xmlText($item, ['list_price', 'retail_price']) !== ''
                ? (float) str_replace(',', '.', $this->xmlText($item, ['list_price', 'retail_price']))
                : null,
            'tax_rate' => $source->tax_rate !== null
                ? (float) $source->tax_rate
                : (float) $this->xmlText($item, ['tax', 'tax_rate', 'TaxRate.rate'], (string) app(PricingService::class)->defaultXmlTaxRate()),
            'desi' => (float) $this->xmlText($item, ['desi', 'Volume'], '1'),
            'stock' => $stock,
            'has_variants' => count($variantData) > 0,
            'is_active' => $stock > 0 || collect($variantData)->contains(fn ($variant) => $variant['stock'] > 0),
            'last_synced_at' => now(),
        ];

        // Görseller
        $images = [];
        // image_count her XML'de doğru gelmediği için en az 15 görsel etiketi taranır.
        $imageCount = max((int) $this->xmlText($item, ['image_count'], '0'), 15);
        for ($i = 1; $i <= $imageCount; $i++) {
            $image = $this->xmlText($item, [
                "Image{$i}", "image_{$i}", "image{$i}", "IMAGE{$i}",
                "Picture{$i}", "picture_{$i}", "img{$i}", "Img{$i}",
            ]);
            if ($image !== '' && (str_starts_with($image, 'http://') || str_starts_with($image, 'https://'))) {
                $images[] = $image;
            }
        }
        // Tek alan
        foreach (['Image', 'image', 'image_url', 'ImageUrl', 'img', 'picture', 'Picture'] as $tag) {
            $one = $this->xmlText($item, [$tag]);
            if ($one !== '' && (str_starts_with($one, 'http://') || str_starts_with($one, 'https://'))) {
                $images[] = $one;
            }
        }
        // <images><image>
        if (isset($item->images->image)) {
            foreach ($item->images->image as $img) {
                $u = trim((string) $img);
                if ($u !== '' && (str_starts_with($u, 'http://') || str_starts_with($u, 'https://'))) {
                    $images[] = $u;
                }
            }
        }
        $data['images'] = array_values(array_unique($images));

        return [
            'data' => $data,
            'variants' => $variantData,
        ];
    }

    protected function upsertProductBatch(array $batch, Source $source): void
    {
        $stockCodes = array_keys($batch);
        $existingStockCodes = Product::query()
            ->where('source_id', $source->id)
            ->whereIn('stock_code', $stockCodes)
            ->pluck('stock_code')
            ->all();
        $existingStockCodeLookup = array_fill_keys($existingStockCodes, true);
        $now = now();
        $products = [];

        foreach ($batch as $stockCode => $product) {
            $products[] = [
                ...$product['data'],
                'images' => json_encode($product['data']['images'], JSON_THROW_ON_ERROR),
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (isset($existingStockCodeLookup[$stockCode])) {
                $this->stats['updated']++;
            } else {
                $this->stats['created']++;
            }
        }

        Product::query()->upsert(
            $products,
            ['stock_code', 'source_id'],
            [
                'barcode',
                'title',
                'brand',
                'description',
                'main_category',
                'sub_category',
                'category_path',
                'price',
                'cost_price',
                'list_price',
                'tax_rate',
                'desi',
                'stock',
                'is_active',
                'has_variants',
                'images',
                'last_synced_at',
                'updated_at',
            ]
        );

        $productsByStockCode = Product::query()
            ->where('source_id', $source->id)
            ->whereIn('stock_code', $stockCodes)
            ->get(['id', 'stock_code'])
            ->keyBy('stock_code');

        $productIds = $productsByStockCode->pluck('id')->all();

        // Varyantlar silinip yeniden yazılmaz: var olan kayıtlar güncellenir.
        // Aksi hâlde her içe aktarmada varyant ID'leri değişiyor ve bayilerin
        // Trendyol kayıtlarındaki varyant bağlantıları kopuyordu.
        $this->syncVariants($batch, $productsByStockCode, $now);

        // Satış fiyatları toplu hesaplanır; ürün başına modele dokunulmaz.
        $pricing = app(PricingService::class);
        $margin = (float) ($source->xml_margin_percent ?? $pricing->defaultXmlMargin());
        $pricing->recalculateImported($productIds, $source->id, $margin);
    }

    /**
     * Varyantları XML verisiyle eşitler: kimliği (barkod > stok kodu > ad+değer)
     * bulunan kayıtlar güncellenir, yeniler eklenir, XML'den düşenler silinir.
     *
     * @param  array<string, array{data: array<string, mixed>, variants: array<int, array<string, mixed>>}>  $batch
     */
    protected function syncVariants(array $batch, \Illuminate\Support\Collection $productsByStockCode, $now): void
    {
        $productIds = $productsByStockCode->pluck('id')->all();
        if ($productIds === []) {
            return;
        }

        $existing = ProductVariant::query()
            ->whereIn('product_id', $productIds)
            ->get()
            ->groupBy('product_id');

        foreach ($batch as $stockCode => $product) {
            $productId = $productsByStockCode->get($stockCode)?->id;
            if ($productId === null) {
                continue;
            }

            $current = $existing->get($productId, collect());
            $matched = [];

            foreach ($product['variants'] as $variant) {
                $key = $this->variantKey($variant);

                $match = $current->first(function (ProductVariant $candidate) use ($key, $matched): bool {
                    if (in_array($candidate->id, $matched, true)) {
                        return false;
                    }

                    return $this->variantKey([
                        'barcode' => $candidate->barcode,
                        'sku' => $candidate->sku,
                        'name' => $candidate->name,
                        'value' => $candidate->value,
                    ]) === $key;
                });

                $data = [
                    'barcode' => ($variant['barcode'] ?? '') !== '' ? $variant['barcode'] : null,
                    'sku' => ($variant['sku'] ?? '') !== '' ? $variant['sku'] : null,
                    'name' => $variant['name'] ?? 'Seçenek',
                    'value' => $variant['value'] ?? '',
                    'color' => ($variant['color'] ?? '') !== '' ? $variant['color'] : null,
                    'stock' => (int) ($variant['stock'] ?? 0),
                    'variant_stock' => (int) ($variant['stock'] ?? 0),
                    'variant_price' => $variant['variant_price'] ?? null,
                    'price_diff' => (float) ($variant['price_diff'] ?? 0),
                ];

                if ($match !== null) {
                    $match->update($data + ['updated_at' => $now]);
                    $matched[] = $match->id;

                    continue;
                }

                ProductVariant::create(
                    ['product_id' => $productId, 'created_at' => $now, 'updated_at' => $now] + $data
                );
            }

            // XML'den çıkmış varyantlar silinir
            $stale = $current->whereNotIn('id', $matched)->pluck('id')->all();
            if ($stale !== []) {
                ProductVariant::query()->whereIn('id', $stale)->delete();
            }
        }
    }

    /**
     * Varyantın kaynak XML'deki kimliği: barkod > stok kodu > ad + değer.
     *
     * @param  array<string, mixed>  $variant
     */
    private function variantKey(array $variant): string
    {
        foreach (['barcode', 'sku'] as $field) {
            $value = mb_strtolower(trim((string) ($variant[$field] ?? '')));
            if ($value !== '') {
                return $field.':'.$value;
            }
        }

        return 'name:'.mb_strtolower(trim((string) ($variant['name'] ?? '')))
            .'|'.mb_strtolower(trim((string) ($variant['value'] ?? '')));
    }

    protected function xmlText(\SimpleXMLElement $item, array $paths, string $default = ''): string
    {
        foreach ($paths as $path) {
            $value = $item;

            foreach (explode('.', $path) as $segment) {
                if (! isset($value->{$segment})) {
                    $value = null;

                    break;
                }

                $value = $value->{$segment};
            }

            if ($value !== null) {
                $text = trim((string) $value);
                if ($text !== '') {
                    return $text;
                }
            }
        }

        return $default;
    }
}
