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

    public function defaultXmlTaxRate(): float
    {
        return (float) PlatformSetting::read('xml_tax_rate', '20');
    }

    public function xmlPricesIncludeTax(): bool
    {
        return PlatformSetting::read('xml_prices_include_tax', '0') === '1';
    }

    /**
     * cost_price (XML alış) üzerinden admin satış fiyatını hesapla.
     */
    public function calculateSellPrice(float $costPrice, ?float $xmlMargin = null, ?float $minMargin = null, ?float $taxRate = null): float
    {
        $margin = $xmlMargin ?? $this->defaultXmlMargin();
        $min = $minMargin ?? $this->defaultMinMargin();
        $margin = max($margin, $min);
        $tax = max(0, $taxRate ?? $this->defaultXmlTaxRate());
        $netCost = $this->xmlPricesIncludeTax() && $tax > 0
            ? $costPrice / (1 + ($tax / 100))
            : $costPrice;

        return round($netCost * (1 + ($margin / 100)) * (1 + ($tax / 100)), 2);
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
        $product->tax_rate = $product->tax_rate ?? $this->defaultXmlTaxRate();
        $product->sell_price = $this->calculateSellPrice($cost, $product->xml_margin_percent, $min, (float) $product->tax_rate);
        // Geriye uyumluluk: price = bayilere görünen fiyat
        $product->price = $product->sell_price;
        $product->save();

        return $product;
    }

    public function bulkApplyXmlMargin(float $marginPercent, bool $overrideTax = false): int
    {
        $count = 0;
        Product::query()->chunkById(100, function ($products) use ($marginPercent, $overrideTax, &$count) {
            foreach ($products as $product) {
                if ($overrideTax) {
                    $product->tax_rate = $this->defaultXmlTaxRate();
                }
                $this->applyToProduct($product, $marginPercent);
                $count++;
            }
        });

        PlatformSetting::write('xml_margin_percent', $marginPercent);

        return $count;
    }
}
