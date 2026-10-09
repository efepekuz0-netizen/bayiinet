<?php

namespace App\Http\Controllers;

use App\Models\Dealer;
use App\Models\Product;
use App\Services\PricingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class XmlFeedController extends Controller
{
    /**
     * Bayiye özel canlı XML feed
     * URL: /xml/{token}.xml
     */
    public function dealerFeed(string $token): Response
    {
        $dealer = Dealer::where('xml_token', $token)
            ->where('status', 'active')
            ->firstOrFail();

        // Bayi bazlı cache (5 dk). Saatlik senkron cache'i temizler.
        $xml = Cache::remember('xml_feed_dealer_'.$dealer->id, 300, function () use ($dealer): string {
            return $this->generateXml($dealer);
        });

        // last_synced bilgisini hafifçe güncelle (isteğe bağlı izleme)
        if (! $dealer->last_synced_at || $dealer->last_synced_at->lt(now()->subMinutes(30))) {
            $dealer->update(['last_synced_at' => now()]);
        }

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'private, max-age=60',
        ]);
    }

    /**
     * Genel / test feed (admin için)
     */
    public function publicFeed(): Response
    {
        $xml = Cache::remember('xml_feed_catalog', 300, function (): string {
            return $this->generateXml(null);
        });

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }

    protected function generateXml(?Dealer $dealer = null): string
    {
        $pricing = app(PricingService::class);
        $dealerMargin = $dealer
            ? (float) ($dealer->default_marketplace_margin ?? $pricing->defaultMarketplaceMargin())
            : null;

        // Bellekte on binlerce ürün tutmamak için parça parça yazılır
        // (SimpleXMLElement ağacı 15.000 üründe onlarca MB yeriyordu).
        $out = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<products>';

        $this->feedQuery()
            ->chunkById(500, function ($products) use (&$out, $pricing, $dealerMargin): void {
                foreach ($products as $product) {
                    $out .= $this->productXml($product, $pricing, $dealerMargin);
                }
            });

        return $out.'</products>';
    }

    protected function feedQuery(): Builder
    {
        return Product::query()
            ->with('variants')
            ->where('is_active', true)
            ->where(function (Builder $query): void {
                $query->where(function (Builder $products): void {
                    $products->where('has_variants', false)->where('stock', '>', 0);
                })->orWhere(function (Builder $products): void {
                    $products->where('has_variants', true)
                        ->whereHas('variants', fn (Builder $variants) => $variants->where('stock', '>', 0));
                });
            })
            ->orderBy('id');
    }

    protected function productXml(Product $product, PricingService $pricing, ?float $dealerMargin): string
    {
        $out = '<product>';
        $out .= $this->el('title', $product->title ?? '');
        $out .= $this->el('stock_code', $product->stock_code ?? '');
        $out .= $this->el('barcode', $product->barcode ?? '');
        $out .= $this->el('brand', $product->brand ?? '');
        $out .= $this->el('category', $product->category_path ?? $product->main_category ?? '');
        $out .= $this->el('main_category', $product->main_category ?? '');
        $out .= $this->el('sub_category', $product->sub_category ?? '');
        $out .= $this->el('stock', (string) $product->effective_stock);

        $salePrice = (float) ($product->sell_price ?? $product->price ?? 0);
        $out .= $this->el('sale_price', number_format($salePrice, 2, '.', ''));
        $out .= $this->el('cost_price', number_format((float) ($product->cost_price ?? $salePrice), 2, '.', ''));

        // Bayiye özel önerilen perakende (bayi karı uygulanmış)
        if ($dealerMargin !== null) {
            $retail = $pricing->calculateDealerRetailPrice($salePrice, $dealerMargin);
            $out .= $this->el('retail_price', number_format($retail, 2, '.', ''));
            $out .= $this->el('dealer_margin_percent', number_format($dealerMargin, 2, '.', ''));
        }

        $out .= $this->el('currency', 'TRY');
        $out .= $this->el('tax', (string) ($product->tax_rate ?? 20));
        $out .= $this->el('desi', number_format((float) ($product->desi ?? 0), 2, '.', ''));
        $out .= $this->el('description', $product->description ?? '');

        $images = is_array($product->images) ? $product->images : [];
        $out .= $this->el('image_count', (string) count($images));
        foreach (array_values($images) as $index => $img) {
            $out .= $this->el('image_'.($index + 1), (string) $img);
        }

        if ($product->has_variants && $product->variants->count() > 0) {
            $out .= '<variants>';
            foreach ($product->variants as $variant) {
                if ((int) $variant->stock <= 0) {
                    continue;
                }
                $out .= '<variant>';
                $out .= $this->el('barcode', $variant->barcode ?? '');
                $out .= $this->el('sku', $variant->sku ?? '');
                $out .= $this->el('stock', (string) $variant->stock);
                $out .= $this->el('name', $variant->name ?? '');
                $out .= $this->el('value', $variant->value ?? '');
                $out .= $this->el('color', $variant->color ?? '');
                // Not: fiyat kolonu `variant_price` (eskiden var olmayan
                // `price` alanı okunuyordu, bu yüzden hep boş çıkıyordu)
                $out .= $this->el(
                    'price',
                    number_format((float) ($variant->variant_price ?? $salePrice + (float) ($variant->price_diff ?? 0)), 2, '.', '')
                );
                $out .= '</variant>';
            }
            $out .= '</variants>';
        }

        return $out.'</product>';
    }

    /**
     * XML metin düğümü üretir: geçersiz karakterleri temizler, & < > " ' kaçırır.
     * (SimpleXMLElement zaten kaçırıyordu; eskiden bir de htmlspecialchars
     * uygulandığı için feed'de &amp;lt; gibi çift kaçırılmış metinler vardı.)
     */
    private function el(string $name, mixed $value): string
    {
        $value = $value === null ? '' : (string) $value;

        // XML 1.0'da geçersiz olan kontrol karakterlerini kaldır
        $value = (string) preg_replace('/[^\x{0009}\x{000A}\x{000D}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $value);

        return '<'.$name.'>'.htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8').'</'.$name.'>';
    }
}
