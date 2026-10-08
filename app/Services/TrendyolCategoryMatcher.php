<?php

namespace App\Services;

use App\Models\PlatformSetting;
use App\Models\Product;
use Illuminate\Support\Facades\Cache;

/**
 * Masaüstü product_onboard.match_category + category_map ile aynı mantık.
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

    public function map(): array
    {
        $raw = PlatformSetting::read('trendyol_category_map', '');
        if ($raw === '') {
            return ['by_xml' => [], 'by_id' => [], 'fallback_id' => 0, 'fallback_brand_id' => 0];
        }
        $data = json_decode($raw, true);

        return is_array($data)
            ? array_merge(['by_xml' => [], 'by_id' => [], 'fallback_id' => 0, 'fallback_brand_id' => 0], $data)
            : ['by_xml' => [], 'by_id' => [], 'fallback_id' => 0, 'fallback_brand_id' => 0];
    }

    public function saveMap(array $data): void
    {
        PlatformSetting::write('trendyol_category_map', json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    public function remember(string $xmlKey, int $catId, string $catName = ''): void
    {
        $data = $this->map();
        $key = mb_strtolower(trim($xmlKey));
        if ($key !== '') {
            $data['by_xml'][$key] = ['id' => $catId, 'name' => $catName];
        }
        if ($catId > 0) {
            $data['by_id'][(string) $catId] = $catName;
        }
        $this->saveMap($data);
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
        $this->saveMap($data);
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

        if ($leaves === []) {
            $fb = $this->fallbackCategoryId();

            return ['id' => $fb > 0 ? $fb : null, 'name' => '', 'reason' => $fb > 0 ? 'fallback' : 'none'];
        }

        $byName = [];
        $byNorm = [];
        foreach ($leaves as $leaf) {
            $n = mb_strtolower($leaf['name']);
            $byName[$n][] = $leaf;
            $byNorm[$this->normalize($leaf['name'])][] = $leaf;
        }

        // 2) XML kategori parçaları / main_category
        $parts = array_filter(array_map('trim', preg_split('/>>>|>|\//', $xmlPath) ?: []));
        $candidates = array_values(array_filter(array_merge(
            [end($parts) ?: '', (string) $product->main_category, (string) $product->sub_category],
            array_reverse($parts),
        )));

        foreach ($candidates as $c) {
            if ($c === '') {
                continue;
            }
            $cl = mb_strtolower($c);
            if (isset($byName[$cl][0])) {
                $hit = $byName[$cl][0];
                $this->remember($xmlPath ?: $c, (int) $hit['id'], $hit['name']);

                return ['id' => (int) $hit['id'], 'name' => $hit['name'], 'reason' => 'name'];
            }
            $cn = $this->normalize($c);
            if ($cn !== '' && isset($byNorm[$cn][0])) {
                $hit = $byNorm[$cn][0];
                $this->remember($xmlPath ?: $c, (int) $hit['id'], $hit['name']);

                return ['id' => (int) $hit['id'], 'name' => $hit['name'], 'reason' => 'norm'];
            }
            if (strlen($cn) >= 4) {
                foreach ($leaves as $leaf) {
                    $ln = $this->normalize($leaf['name']);
                    if ($cn === $ln || str_contains($ln, $cn) || str_contains($cn, $ln)) {
                        $this->remember($xmlPath ?: $c, (int) $leaf['id'], $leaf['name']);

                        return ['id' => (int) $leaf['id'], 'name' => $leaf['name'], 'reason' => 'partial'];
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
            if (isset($byName[$tn][0])) {
                $hit = $byName[$tn][0];
                $this->remember($xmlPath ?: $needle, (int) $hit['id'], $hit['name']);

                return ['id' => (int) $hit['id'], 'name' => $hit['name'], 'reason' => 'keyword:'.$tyName];
            }
            $nn = $this->normalize($tyName);
            if (isset($byNorm[$nn][0])) {
                $hit = $byNorm[$nn][0];
                $this->remember($xmlPath ?: $needle, (int) $hit['id'], $hit['name']);

                return ['id' => (int) $hit['id'], 'name' => $hit['name'], 'reason' => 'keyword:'.$tyName];
            }
        }

        // 4) Token skoru
        $tokens = array_values(array_filter(explode(' ', $blob), fn ($t) => strlen($t) >= 4));
        $best = null;
        $bestScore = 0;
        foreach ($leaves as $leaf) {
            $leafN = $this->normalize($leaf['name']);
            $pathN = $this->normalize($leaf['path']);
            $score = 0;
            foreach ($tokens as $t) {
                if (str_contains($leafN, $t)) {
                    $score += 3;
                } elseif (str_contains($pathN, $t)) {
                    $score += 1;
                }
            }
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $leaf;
            }
        }
        if ($best && $bestScore >= 1) {
            $this->remember($xmlPath ?: mb_substr($title, 0, 40), (int) $best['id'], $best['name']);

            return ['id' => (int) $best['id'], 'name' => $best['name'], 'reason' => 'score:'.$bestScore];
        }

        $fb = $this->fallbackCategoryId();

        return ['id' => $fb > 0 ? $fb : null, 'name' => '', 'reason' => $fb > 0 ? 'fallback' : 'none'];
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

        return trim(preg_replace('/\s+/', ' ', $s) ?? $s);
    }
}
