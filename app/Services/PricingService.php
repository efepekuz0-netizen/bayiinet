<?php

namespace App\Services;

use App\Models\PlatformSetting;
use App\Models\Product;
use App\Models\Source;
use Illuminate\Support\Facades\DB;

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
        $product->xml_margin_percent = max((float) $margin, (float) $min);
        $product->tax_rate = $product->tax_rate ?: $settings['tax_rate'];
        $product->sell_price = $this->calculateSellPriceWithSettings(
            $cost,
            (float) $product->xml_margin_percent,
            (float) $min,
            (float) $product->tax_rate,
            (bool) $settings['include_tax']
        );
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

    /**
     * Performans odaklı toplu kar oranı uygulaması.
     * Model event'leri ve N+1 olmadan, büyük chunk'larla günceller.
     */
    public function bulkApplyXmlMargin(float $marginPercent, bool $overrideTax = false, ?int $sourceId = null): int
    {
        $defaults = $this->defaults();
        $globalMin = $defaults['min_margin'];
        $globalTax = $defaults['tax_rate'];
        $globalIncludeTax = $defaults['include_tax'];

        // Kaynak ayarlarını bir kez yükle
        $sourceSettings = [];
        if ($sourceId === null) {
            Source::query()->select(['id', 'xml_margin_percent', 'min_margin_percent', 'tax_rate', 'prices_include_tax'])
                ->get()
                ->each(function (Source $s) use (&$sourceSettings) {
                    $sourceSettings[$s->id] = [
                        'min' => $s->min_margin_percent !== null ? (float) $s->min_margin_percent : null,
                        'tax' => $s->tax_rate !== null ? (float) $s->tax_rate : null,
                        'include_tax' => $s->prices_include_tax,
                    ];
                });
        } else {
            $s = Source::query()->find($sourceId);
            if ($s) {
                $sourceSettings[$s->id] = [
                    'min' => $s->min_margin_percent !== null ? (float) $s->min_margin_percent : null,
                    'tax' => $s->tax_rate !== null ? (float) $s->tax_rate : null,
                    'include_tax' => $s->prices_include_tax,
                ];
            }
        }

        $count = 0;
        $now = now()->toDateTimeString();

        Product::query()
            ->when($sourceId, fn ($q) => $q->where('source_id', $sourceId))
            ->select(['id', 'source_id', 'cost_price', 'price', 'tax_rate', 'min_margin_percent'])
            ->orderBy('id')
            ->chunkById(250, function ($products) use (
                $marginPercent,
                $overrideTax,
                $sourceSettings,
                $globalMin,
                $globalTax,
                $globalIncludeTax,
                $now,
                &$count
            ) {
                $updates = [];

                foreach ($products as $product) {
                    $cost = (float) ($product->cost_price ?? $product->price ?? 0);
                    $src = $sourceSettings[$product->source_id] ?? null;

                    $min = $src['min'] ?? ($product->min_margin_percent !== null
                        ? (float) $product->min_margin_percent
                        : $globalMin);
                    $effectiveMargin = max($marginPercent, (float) $min);

                    $tax = $overrideTax
                        ? ($src['tax'] ?? $globalTax)
                        : (float) ($product->tax_rate ?: ($src['tax'] ?? $globalTax));

                    $includeTax = $src['include_tax'] ?? $globalIncludeTax;

                    $netCost = $includeTax && $tax > 0
                        ? $cost / (1 + ($tax / 100))
                        : $cost;
                    $sell = round($netCost * (1 + ($effectiveMargin / 100)) * (1 + ($tax / 100)), 2);

                    $updates[] = [
                        'id' => $product->id,
                        'cost_price' => $cost,
                        'xml_margin_percent' => $effectiveMargin,
                        'tax_rate' => $tax,
                        'sell_price' => $sell,
                        'price' => $sell,
                    ];
                    $count++;
                }

                if ($updates !== []) {
                    foreach (array_chunk($updates, 100) as $batch) {
                        $casesCost = [];
                        $casesMargin = [];
                        $casesTax = [];
                        $casesSell = [];
                        $casesPrice = [];
                        $ids = [];

                        foreach ($batch as $row) {
                            $id = (int) $row['id'];
                            $ids[] = $id;
                            $casesCost[] = "WHEN {$id} THEN " . number_format($row['cost_price'], 4, '.', '');
                            $casesMargin[] = "WHEN {$id} THEN " . number_format($row['xml_margin_percent'], 4, '.', '');
                            $casesTax[] = "WHEN {$id} THEN " . number_format($row['tax_rate'], 2, '.', '');
                            $casesSell[] = "WHEN {$id} THEN " . number_format($row['sell_price'], 4, '.', '');
                            $casesPrice[] = "WHEN {$id} THEN " . number_format($row['price'], 4, '.', '');
                        }

                        $idList = implode(',', $ids);
                        $sql = "UPDATE products SET
                            cost_price = CASE id " . implode(' ', $casesCost) . " END,
                            xml_margin_percent = CASE id " . implode(' ', $casesMargin) . " END,
                            tax_rate = CASE id " . implode(' ', $casesTax) . " END,
                            sell_price = CASE id " . implode(' ', $casesSell) . " END,
                            price = CASE id " . implode(' ', $casesPrice) . " END,
                            updated_at = ?
                            WHERE id IN ({$idList})";

                        DB::update($sql, [$now]);
                    }
                }
            });

        if ($sourceId === null) {
            PlatformSetting::write('xml_margin_percent', $marginPercent);
        }

        return $count;
    }
}
