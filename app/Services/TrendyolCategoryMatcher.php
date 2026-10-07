<?php

namespace App\Services;

use App\Models\PlatformSetting;
use App\Models\Product;

/**
 * Masaüstü category_map.py ile aynı mantık:
 * - Kaydedilmiş XML yolu / başlık eşlemesi
 * - Anahtar kelime yedekleri
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
        ['usb', 'Şarj Kablosu'],
        ['kablo', 'Şarj Kablosu'],
        ['buhar', 'Hava Nemlendirici'],
        ['vakum', 'El Süpürgesi'],
        ['yüz tüy', 'Epilatör'],
        ['swab', 'Kulak Temizleme Cihazı'],
        ['bıçak', 'Çakı'],
        ['dolap', 'Ecza Dolabı'],
        ['powerbank', 'Powerbank'],
        ['power bank', 'Powerbank'],
        ['fener', 'El Feneri'],
        ['kamp', 'Kamp Malzemeleri'],
        ['demlik', 'Çaydanlık'],
        ['çay', 'Çaydanlık'],
        ['lamba', 'Masa Lambası'],
        ['hacamat', 'Hacamat Seti'],
    ];

    public function map(): array
    {
        $raw = PlatformSetting::read('trendyol_category_map', '');
        if ($raw === '') {
            return ['by_xml' => [], 'by_id' => [], 'fallback_id' => 0, 'fallback_brand_id' => 0];
        }

        $data = json_decode($raw, true);

        return is_array($data) ? array_merge(
            ['by_xml' => [], 'by_id' => [], 'fallback_id' => 0, 'fallback_brand_id' => 0],
            $data
        ) : ['by_xml' => [], 'by_id' => [], 'fallback_id' => 0, 'fallback_brand_id' => 0];
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
        $data['fallback_id'] = $categoryId;
        if ($brandId > 0) {
            $data['fallback_brand_id'] = $brandId;
        }
        $this->saveMap($data);
    }

    /**
     * Ürün için Trendyol kategori id önerisi.
     * @return array{id: int|null, reason: string}
     */
    public function resolve(Product $product, ?int $overrideCategoryId = null): array
    {
        if ($overrideCategoryId && $overrideCategoryId > 0) {
            return ['id' => $overrideCategoryId, 'reason' => 'manual'];
        }

        $map = $this->map();
        $keys = array_filter([
            $product->category_path,
            $product->main_category,
            $product->sub_category,
            $product->title,
        ]);

        foreach ($keys as $k) {
            $lk = mb_strtolower(trim((string) $k));
            if ($lk !== '' && isset($map['by_xml'][$lk]['id'])) {
                return ['id' => (int) $map['by_xml'][$lk]['id'], 'reason' => 'saved:'.$lk];
            }
        }

        $blob = $this->normalize(implode(' ', [
            $product->title,
            $product->category_path,
            $product->main_category,
            $product->sub_category,
        ]));

        foreach (self::KEYWORDS as [$needle, $label]) {
            if (str_contains($blob, $this->normalize($needle))) {
                // Kaydedilmiş id varsa onu kullan
                foreach ($map['by_xml'] as $entry) {
                    if (isset($entry['name']) && $this->normalize($entry['name']) === $this->normalize($label) && ! empty($entry['id'])) {
                        return ['id' => (int) $entry['id'], 'reason' => 'keyword:'.$label];
                    }
                }

                return ['id' => null, 'reason' => 'keyword_need_id:'.$label];
            }
        }

        $fb = (int) ($map['fallback_id'] ?? 0);

        return ['id' => $fb > 0 ? $fb : null, 'reason' => $fb > 0 ? 'fallback' : 'none'];
    }

    public function fallbackBrandId(): int
    {
        return (int) ($this->map()['fallback_brand_id'] ?? 0);
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
