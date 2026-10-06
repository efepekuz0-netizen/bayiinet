<?php

namespace App\Services;

use App\Models\PlatformSetting;
use App\Models\Product;

class PricingService
{
    public function defaultXmlMargin(): float
    {
        return (float) PlatformSetting::read('xml_margin_percent', '15');
    }

    public function defaultMinMargin(): float
    {
        return (float) PlatformSetting::read('min_margin_percent', '5');
    }

    public function defaultMarketplaceMargin(): float
    {
        return (float) PlatformSetting::read('default_marketplace_margin', '20');
    }

    /**
     * cost_price (XML alış) üzerinden admin satış fiyatını hesapla.
     */
    public function calculateSellPrice(float $costPrice, ?float $xmlMargin = null, ?float $minMargin = null): float
    {
        $margin = $xmlMargin ?? $this->defaultXmlMargin();
        $min = $minMargin ?? $this->defaultMinMargin();
        $margin = max($margin, $min);

        return round($costPrice * (1 + ($margin / 100)), 2);
    }

    /**
     * Bayinin pazaryeri satış fiyatı (bizim sell_price + bayi karı).
     */
    public function calculateDealerRetailPrice(float $sellPrice, float $dealerMargin): float
    {
        return round($sellPrice * (1 + ($dealerMargin / 100)), 2);
    }

    public function applyToProduct(Product $product, ?float $xmlMargin = null): Product
    {
        $cost = (float) ($product->cost_price ?? $product->price ?? 0);
        $margin = $xmlMargin ?? $product->xml_margin_percent ?? $this->defaultXmlMargin();
        $min = $product->min_margin_percent ?? $this->defaultMinMargin();

        $product->cost_price = $cost;
        $product->xml_margin_percent = max($margin, $min);
        $product->sell_price = $this->calculateSellPrice($cost, $product->xml_margin_percent, $min);
        // Geriye uyumluluk: price = bayilere görünen fiyat
        $product->price = $product->sell_price;
        $product->save();

        return $product;
    }

    public function bulkApplyXmlMargin(float $marginPercent): int
    {
        $count = 0;
        Product::query()->chunkById(100, function ($products) use ($marginPercent, &$count) {
            foreach ($products as $product) {
                $this->applyToProduct($product, $marginPercent);
                $count++;
            }
        });

        PlatformSetting::write('xml_margin_percent', $marginPercent);

        return $count;
    }
}
