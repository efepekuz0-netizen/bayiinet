<?php

namespace App\Services;

use App\Models\PlatformSetting;
use App\Models\Product;
use Illuminate\Support\Facades\Cache;

/**
 * Masaüstü product_onboard.match_category + category_map ile aynı mantık.
 *
 * Performans notu: kategori ağacı (binlerce yaprak) her ürün için yeniden
 * normalize ediliyordu. Artık yaprak listesi bir kez indeksleniyor ve
 * bulunan eşleşmeler kategori haritasına yazılıp sonraki ürünlerde
 * doğrudan kullanılıyor.
 */
class TrendyolCategoryMatcher
{
    /** @var list<array{0: string, 1: string}> */
    public const KEYWORDS = [
        ['klima kumanda', 'Klima Kumandası'],
        ['kumanda', 'Kumanda'],
        ['pompa motor', 'Pompa'],
        ['pompa', 'Pompa'],
        ['terazi', 'Terazi'],
        ['tartı', 'Terazi'],
        ['epilat', 'Epilatör'],
        ['tıraş', 'Tıraş Makinesi'],
        ['nemlendirici', 'Hava Nemlendirici'],
        ['süpürge', 'El Süpürgesi'],
        ['cibinlik', 'Cibinlik'],
        ['ecza dolab', 'Ecza Dolabı'],
        ['kart bıçak', 'Çakı'],
        ['kulak temiz', 'Kulak Temizleme Cihazı'],
        ['şarj kablo', 'Şarj Kablosu'],
        ['usb kablo', 'Şarj Kablosu'],
        ['powerbank', 'Powerbank'],
        ['power bank', 'Powerbank'],
        ['fener', 'El Feneri'],
        ['kamp fener', 'El Feneri'],
        ['demlik', 'Çaydanlık'],
        ['çay keyfi', 'Çaydanlık'],
        ['lamba', 'Masa Lambası'],
        ['hacamat', 'Hacamat Seti'],
        ['buhar', 'Hava Nemlendirici'],
        ['vakum', 'El Süpürgesi'],
        ['bıçak', 'Çakı'],
        ['dolap', 'Ecza Dolabı'],
        ['magsafe', 'Powerbank'],
        ['mag safe', 'Powerbank'],
        ['kablosuz sarj', 'Kablosuz Şarj Cihazı'],
        ['kablosuz şarj', 'Kablosuz Şarj Cihazı'],
        ['wireless', 'Kablosuz Şarj Cihazı'],
        ['ios uyumlu', 'Powerbank'],
        ['sarj aleti', 'Şarj Aleti'],
        ['şarj aleti', 'Şarj Aleti'],
        ['adaptor', 'Şarj Aleti'],
        ['adaptör', 'Şarj Aleti'],
        ['kulaklik', 'Kulaklık'],
        ['kulaklık', 'Kulaklık'],
        ['bluetooth', 'Kulaklık'],
        ['telefon kilif', 'Telefon Kılıfı'],
        ['telefon kılıf', 'Telefon Kılıfı'],
        ['kılıf', 'Telefon Kılıfı'],
        ['ekran koruyucu', 'Ekran Koruyucu'],
        ['temperli', 'Ekran Koruyucu'],
        ['selfi', 'Selfie Çubuğu'],
        ['selfie', 'Selfie Çubuğu'],
        ['tripod', 'Tripod'],
        ['mouse', 'Mouse'],
        ['klavye', 'Klavye'],
        ['hoparlor', 'Hoparlör'],
        ['hoparlör', 'Hoparlör'],
        ['speaker', 'Hoparlör'],
        ['saat', 'Akıllı Saat'],
        ['akilli saat', 'Akıllı Saat'],
        ['watch', 'Akıllı Saat'],
        ['tablet', 'Tablet'],
        ['kamera', 'Aksiyon Kamera'],
        ['drone', 'Drone'],
    ];

    /** @var array<string, mixed>|null */
    private ?array $mapCache = null;

    private bool $mapDirty = false;

    /** Yaprak listesi için hazırlanan indeks */
    private array $index = [];

    private ?string $indexKey = null;

    /** Bu çalışmada eşleşme bulunamayan kategori anahtarları (tekrar taramamak için) */
    private array $missed = [];

    public function map(): array
    {
        if ($this->mapCache !== null) {
            return $this->mapCache;
        }

        return $this->mapCache = $this->loadFromDb();
    }

    private function loadFromDb(): array
    {
        $empty = ['by_xml' => [], 'by_id' => [], 'fallback_id' => 0, 'fallback_brand_id' => 0];
        $raw = (string) PlatformSetting::read('trendyol_category_map', '');

        if ($raw === '') {
            return $empty;
        }

        $data = json_decode($raw, true);

        return is_array($data) ? array_merge($empty, $data) : $empty;
    }

    /**
     * Haritayı doğrudan yazar (yönetim panelinden kaydedilen elle eşleme).
     */
    public function saveMap(array $data): void
    {
        $current = $this->map();
        $this->mapCache = array_merge($current, $data);
        $this->persist();
    }

    /**
     * Bulunan eşleşmeyi kaydeder. Yazma işlemi toplu yapılır (flush),
     * aksi hâlde her ürün için bir veritabanı yazması oluşuyordu.
     */
    public function remember(string $xmlKey, int $catId, string $catName = ''): void
    {
        $map = $this->map();
        $key = mb_strtolower(trim($xmlKey));

        if ($key !== '') {
            $map['by_xml'][$key] = ['id' => $catId, 'name' => $catName];
        }
        if ($catId > 0) {
            $map['by_id'][(string) $catId] = $catName;
        }

        $this->mapCache = $map;
        $this->mapDirty = true;
    }

    /**
     * Bellekte biriken eşleme değişikliklerini veritabanına yazar.
     */
    public function flush(): void
    {
        if (! $this->mapDirty) {
            return;
        }

        $this->persist(false);
    }

    /**
     * @param  bool  $replace  true: harita olduğu gibi yazılır (yönetim paneli).
     *                         false: veritabanındaki haritayla birleştirilir
     *                         (aynı anda çalışan kuyruk işlerinin bulduğu
     *                         eşleşmeler birbirini ezmesin).
     */
    private function persist(bool $replace = true): void
    {
        $current = $this->mapCache ?? $this->loadFromDb();

        if (! $replace) {
            $fresh = $this->loadFromDb();
            $current = [
                'by_xml' => array_merge($fresh['by_xml'], $current['by_xml'] ?? []),
                'by_id' => array_merge($fresh['by_id'], $current['by_id'] ?? []),
                'fallback_id' => (int) ($current['fallback_id'] ?? 0) ?: (int) ($fresh['fallback_id'] ?? 0),
                'fallback_brand_id' => (int) ($current['fallback_brand_id'] ?? 0) ?: (int) ($fresh['fallback_brand_id'] ?? 0),
            ];
        }

        PlatformSetting::write('trendyol_category_map', json_encode($current, JSON_UNESCAPED_UNICODE));
        $this->mapCache = $current;
        $this->mapDirty = false;
    }

    public function setFallback(int $categoryId, int $brandId = 0): void
    {
        $data = $this->map();
        if ($categoryId > 0) {
            $data['fallback_id'] = $categoryId;
        }
        if ($brandId > 0) {
            $data['fallback_brand_id'] = $brandId;
        }
        $this->mapCache = $data;
        $this->persist();
    }

    public function fallbackBrandId(): int
    {
        return (int) ($this->map()['fallback_brand_id'] ?? 0);
    }

    public function fallbackCategoryId(): int
    {
        return (int) ($this->map()['fallback_id'] ?? 0);
    }

    /**
     * @param  list<array{id:int,name:string,path:string}>  $leaves
     * @return array{id: int|null, name: string, reason: string}
     */
    public function match(Product $product, array $leaves, ?int $overrideCategoryId = null): array
    {
        if ($overrideCategoryId && $overrideCategoryId > 0) {
            return ['id' => $overrideCategoryId, 'name' => '', 'reason' => 'manual'];
        }

        $map = $this->map();
        $xmlPath = (string) ($product->category_path ?? '');
        $title = (string) ($product->title ?? '');
        $mapKey = mb_strtolower(trim($xmlPath !== '' ? $xmlPath : $title));

        // 1) Kaydedilmiş eşleme
        foreach (array_filter([$xmlPath, $product->main_category, $product->sub_category, $title]) as $k) {
            $lk = mb_strtolower(trim((string) $k));
            if ($lk !== '' && isset($map['by_xml'][$lk]['id'])) {
                $id = (int) $map['by_xml'][$lk]['id'];
                foreach ($leaves as $leaf) {
                    if ((int) $leaf['id'] === $id) {
                        return ['id' => $id, 'name' => $leaf['name'], 'reason' => 'saved'];
                    }
                }

                return ['id' => $id, 'name' => (string) ($map['by_xml'][$lk]['name'] ?? ''), 'reason' => 'saved'];
            }
        }

        // Bu kategori için daha önce hiç sonuç bulunamadıysa tekrar tarama yapma
        if ($mapKey !== '' && isset($this->missed[$mapKey])) {
            return $this->fallbackResult();
        }

        if ($leaves === []) {
            return $this->fallbackResult();
        }

        $idx = $this->indexFor($leaves);

        // 2) XML kategori parçaları / main_category
        $parts = array_values(array_filter(array_map('trim', preg_split('/>>>|>|\\//', $xmlPath) ?: [])));
        $candidates = array_values(array_filter(array_merge(
            [$parts !== [] ? end($parts) : '', (string) $product->main_category, (string) $product->sub_category],
            array_reverse($parts),
        )));

        foreach ($candidates as $c) {
            if ($c === '') {
                continue;
            }
            $cl = mb_strtolower($c);
            if (isset($idx['byName'][$cl][0])) {
                return $this->hit($idx, $idx['byName'][$cl][0], 'name', $xmlPath ?: $c);
            }
            $cn = $this->normalize($c);
            if ($cn !== '' && isset($idx['byNorm'][$cn][0])) {
                return $this->hit($idx, $idx['byNorm'][$cn][0], 'norm', $xmlPath ?: $c);
            }
            if (strlen($cn) >= 4) {
                foreach ($idx['norms'] as $i => $ln) {
                    if ($cn === $ln || str_contains($ln, $cn) || str_contains($cn, $ln)) {
                        return $this->hit($idx, $i, 'partial', $xmlPath ?: $c);
                    }
                }
            }
        }

        // 3) Anahtar kelimeler
        $blob = $this->normalize(implode(' ', [$title, $xmlPath, (string) $product->main_category]));
        foreach (self::KEYWORDS as [$needle, $tyName]) {
            if (! str_contains($blob, $this->normalize($needle))) {
                continue;
            }
            $tn = mb_strtolower($tyName);
            if (isset($idx['byName'][$tn][0])) {
                return $this->hit($idx, $idx['byName'][$tn][0], 'keyword:'.$tyName, $xmlPath ?: $needle);
            }
            $nn = $this->normalize($tyName);
            if (isset($idx['byNorm'][$nn][0])) {
                return $this->hit($idx, $idx['byNorm'][$nn][0], 'keyword:'.$tyName, $xmlPath ?: $needle);
            }
        }

        // 4) Token skoru (ters indeks üzerinden)
        $tokens = array_values(array_unique(array_filter(explode(' ', $blob), fn ($t): bool => strlen($t) >= 4)));
        $scores = [];
        $nameHits = [];
        foreach ($tokens as $t) {
            foreach ($idx['tokens'][$t] ?? [] as $i) {
                $scores[$i] = ($scores[$i] ?? 0) + 3;
                $nameHits[$i][$t] = true;
            }
            foreach ($idx['pathTokens'][$t] ?? [] as $i) {
                if (! isset($nameHits[$i][$t])) {
                    $scores[$i] = ($scores[$i] ?? 0) + 1;
                }
            }
        }

        if ($scores !== []) {
            arsort($scores);
            $bestIndex = (int) array_key_first($scores);
            $bestScore = (int) reset($scores);

            if ($bestScore >= 1) {
                return $this->hit(
                    $idx,
                    $bestIndex,
                    'score:'.$bestScore,
                    $xmlPath ?: mb_substr($title, 0, 40)
                );
            }
        }

        if ($mapKey !== '') {
            $this->missed[$mapKey] = true;
        }

        return $this->fallbackResult();
    }

    /**
     * @return array{id: int|null, name: string, reason: string}
     */
    private function fallbackResult(): array
    {
        $fb = $this->fallbackCategoryId();

        return ['id' => $fb > 0 ? $fb : null, 'name' => '', 'reason' => $fb > 0 ? 'fallback' : 'none'];
    }

    /**
     * @return array{id: int, name: string, reason: string}
     */
    private function hit(array $idx, int $i, string $reason, string $rememberKey): array
    {
        $leaf = $idx['leaves'][$i];
        $this->remember($rememberKey, (int) $leaf['id'], (string) $leaf['name']);

        return ['id' => (int) $leaf['id'], 'name' => (string) $leaf['name'], 'reason' => $reason];
    }

    /**
     * Yaprak listesini bir kez indeksler (normalize edilmiş ad, yol ve
     * ters token indeksi). Aynı liste tekrar gelirse indeks yeniden kurulmaz.
     *
     * @param  list<array{id:int,name:string,path:string}>  $leaves
     * @return array{leaves: array, byName: array, byNorm: array, norms: array, tokens: array, pathTokens: array}
     */
    private function indexFor(array $leaves): array
    {
        $key = count($leaves).':'.md5(implode(',', array_column($leaves, 'id')));
        if ($this->indexKey === $key) {
            return $this->index;
        }
        $this->indexKey = $key;

        $byName = [];
        $byNorm = [];
        $norms = [];
        $tokens = [];
        $pathTokens = [];

        foreach ($leaves as $i => $leaf) {
            $name = (string) ($leaf['name'] ?? '');
            $byName[mb_strtolower($name)][] = $i;

            $norm = $this->normalize($name);
            $byNorm[$norm][] = $i;
            $norms[$i] = $norm;

            foreach ($this->tokensOf($norm) as $t) {
                $tokens[$t][] = $i;
            }
            foreach ($this->tokensOf($this->normalize((string) ($leaf['path'] ?? ''))) as $t) {
                $pathTokens[$t][] = $i;
            }
        }

        return $this->index = [
            'leaves' => $leaves,
            'byName' => $byName,
            'byNorm' => $byNorm,
            'norms' => $norms,
            'tokens' => $tokens,
            'pathTokens' => $pathTokens,
        ];
    }

    /**
     * @return list<string>
     */
    private function tokensOf(string $normalized): array
    {
        return array_values(array_unique(array_filter(
            explode(' ', $normalized),
            fn (string $t): bool => strlen($t) >= 4
        )));
    }

    private function normalize(string $s): string
    {
        $s = mb_strtolower(trim($s));
        $map = [
            'ı' => 'i', 'İ' => 'i', 'ç' => 'c', 'Ç' => 'c', 'ö' => 'o', 'Ö' => 'o',
            'ş' => 's', 'Ş' => 's', 'ü' => 'u', 'Ü' => 'u', 'ğ' => 'g', 'Ğ' => 'g',
        ];
        $s = strtr($s, $map);
        $s = preg_replace('/[^a-z0-9]+/u', ' ', $s) ?? $s;

        return trim(preg_replace('/\\s+/', ' ', $s) ?? $s);
    }
}
