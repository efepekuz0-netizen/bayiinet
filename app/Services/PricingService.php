<?php

namespace App\Services;

use App\Models\PlatformSetting;
use App\Models\Product;
use App\Models\Source;

class PricingService
{
    private ?array $defaults = null;

    private function defaults(): array
    {
        return $this->defaults ??= [
            'margin' => (float) PlatformSetting::read('xml_margin_percent', '15'),
            'min_margin' => (float) PlatformSetting::read('min_margin_percent', '5'),
            'marketplace_margin' => (float) PlatformSetting::read('default_marketplace_margin', '20'),
            'tax_rate' => (float) PlatformSetting::read('xml_tax_rate', '20'),
            'include_tax' => PlatformSetting::read('xml_prices_include_tax', '0') === '1',
        ];
    }
    public function defaultXmlMargin(): float
    {
        return $this->defaults()['margin'];
    }

    public function defaultMinMargin(): float
    {
        return $this->defaults()['min_margin'];
    }

    public function defaultMarketplaceMargin(): float
    {
        return $this->defaults()['marketplace_margin'];
    }

    public function defaultXmlTaxRate(): float
    {
        return $this->defaults()['tax_rate'];
    }

    public function xmlPricesIncludeTax(): bool
    {
        return $this->defaults()['include_tax'];
    }

    public function settingsFor(?Source $source = null): array
    {
        return [
            'margin' => (float) ($source?->xml_margin_percent ?? $this->defaultXmlMargin()),
            'min_margin' => (float) ($source?->min_margin_percent ?? $this->defaultMinMargin()),
            'tax_rate' => (float) ($source?->tax_rate ?? $this->defaultXmlTaxRate()),
            'include_tax' => $source?->prices_include_tax ?? $this->xmlPricesIncludeTax(),
        ];
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
        $source = $product->relationLoaded('source') ? $product->source : $product->source()->first();
        $settings = $this->settingsFor($source);
        $margin = $xmlMargin ?? ($source?->xml_margin_percent ?? $product->xml_margin_percent ?? $this->defaultXmlMargin());
        $min = $source?->min_margin_percent ?? $product->min_margin_percent ?? $this->defaultMinMargin();

        $product->cost_price = $cost;
        $product->xml_margin_percent = max($margin, $min);
        $product->tax_rate = $product->tax_rate ?: $settings['tax_rate'];
        $product->sell_price = $this->calculateSellPriceWithSettings($cost, $product->xml_margin_percent, $min, (float) $product->tax_rate, (bool) $settings['include_tax']);
        // Geriye uyumluluk: price = bayilere görünen fiyat
        $product->price = $product->sell_price;
        $product->save();

        return $product;
    }

    public function calculateSellPriceWithSettings(float $cost, float $margin, float $min, float $tax, bool $includeTax): float
    {
        $effectiveMargin = max($margin, $min);
        $netCost = $includeTax && $tax > 0 ? $cost / (1 + ($tax / 100)) : $cost;
        return round($netCost * (1 + ($effectiveMargin / 100)) * (1 + ($tax / 100)), 2);
    }

    public function bulkApplyXmlMargin(float $marginPercent, bool $overrideTax = false, ?int $sourceId = null): int
    {
        $count = 0;
        Product::query()->when($sourceId, fn ($q) => $q->where('source_id', $sourceId))->with('source')->chunkById(100, function ($products) use ($marginPercent, $overrideTax, &$count) {
            foreach ($products as $product) {
                if ($overrideTax) {
                    $product->tax_rate = $product->source?->tax_rate ?? $this->defaultXmlTaxRate();
                }
                $this->applyToProduct($product, $marginPercent);
                $count++;
            }
        });

        if ($sourceId === null) {
            PlatformSetting::write('xml_margin_percent', $marginPercent);
        }

        return $count;
    }
}
