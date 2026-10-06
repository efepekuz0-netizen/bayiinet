<?php

namespace App\Services;

use InvalidArgumentException;

/**
 * Trendyol "Ürün Oluşturma v2" isteği için tek bir ürün kalemini hazırlar.
 * Laravel'e bağımlı değildir; yalnızca dizi alır, dizi döndürür.
 */
class TrendyolProductPayload
{
    public const MAX_IMAGES = 8;

    /**
     * @param array{
     *   barcode: string,
     *   stock_code: string,
     *   title: string,
     *   description?: ?string,
     *   images?: array<int, string>,
     *   quantity: int|float|string,
     *   sale_price: int|float|string,
     *   list_price?: int|float|string|null,
     *   vat_rate: int|string,
     *   desi?: int|float|string|null,
     *   category_id: int|string,
     *   brand_id: int|string,
     *   attributes?: array<int, array<string, mixed>>
     * } $d
     */
    public static function item(array $d): array
    {
        $barcode = self::cleanBarcode((string) ($d['barcode'] ?? ''));
        if ($barcode === '') {
            throw new InvalidArgumentException('Barkod boş.');
        }
        if (mb_strlen($barcode) > 40) {
            throw new InvalidArgumentException('Barkod 40 karakterden uzun.');
        }

        $title = trim((string) ($d['title'] ?? ''));
        if ($title === '') {
            throw new InvalidArgumentException('Ürün adı boş.');
        }

        $stockCode = trim((string) ($d['stock_code'] ?? ''));
        if ($stockCode === '') {
            $stockCode = $barcode;
        }
        $stockCode = mb_substr($stockCode, 0, 100);

        $salePrice = round((float) ($d['sale_price'] ?? 0), 2);
        if ($salePrice <= 0) {
            throw new InvalidArgumentException('Satış fiyatı sıfırdan büyük olmalı.');
        }
        $listPrice = round((float) ($d['list_price'] ?? 0), 2);
        if ($listPrice < $salePrice) {
            $listPrice = $salePrice; // Trendyol: liste fiyatı satış fiyatından düşük olamaz
        }

        $categoryId = (int) ($d['category_id'] ?? 0);
        $brandId = (int) ($d['brand_id'] ?? 0);
        if ($categoryId <= 0) {
            throw new InvalidArgumentException('Trendyol kategori numarası eksik.');
        }
        if ($brandId <= 0) {
            throw new InvalidArgumentException('Trendyol marka numarası eksik.');
        }

        $images = [];
        foreach ((array) ($d['images'] ?? []) as $url) {
            $url = trim((string) $url);
            if (str_starts_with(strtolower($url), 'https://') && ! in_array($url, array_column($images, 'url'), true)) {
                $images[] = ['url' => $url];
            }
            if (count($images) >= self::MAX_IMAGES) {
                break;
            }
        }
        if ($images === []) {
            throw new InvalidArgumentException('Trendyol için en az bir https:// görsel adresi gerekli.');
        }

        $description = trim((string) ($d['description'] ?? ''));
        if ($description === '') {
            $description = $title;
        }

        return [
            'barcode' => $barcode,
            'title' => mb_substr($title, 0, 100),
            'description' => mb_substr($description, 0, 30000),
            'productMainId' => mb_substr($stockCode, 0, 40),
            'brandId' => $brandId,
            'categoryId' => $categoryId,
            'quantity' => max(0, min((int) ($d['quantity'] ?? 0), 20000)),
            'stockCode' => $stockCode,
            'dimensionalWeight' => max(0.1, round((float) ($d['desi'] ?? 1), 2)),
            'listPrice' => $listPrice,
            'salePrice' => $salePrice,
            'vatRate' => (int) ($d['vat_rate'] ?? 10),
            'images' => $images,
            'attributes' => array_values((array) ($d['attributes'] ?? [])),
        ];
    }

    /**
     * Trendyol barkodunda harf, rakam, nokta, tire ve alt çizgiye izin verilir.
     */
    public static function cleanBarcode(string $barcode): string
    {
        return (string) preg_replace('/[^\p{L}\p{N}._\-]/u', '', trim($barcode));
    }
}
