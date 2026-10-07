<?php

namespace App\Http\Controllers;

use App\Models\Dealer;
use App\Models\Product;
use App\Services\PricingService;
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
        $xml = Cache::remember('xml_feed_dealer_'.$dealer->id, 300, function () use ($dealer) {
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
        $xml = Cache::remember('xml_feed_catalog', 300, function () {
            return $this->generateXml(null);
        });

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }

    protected function generateXml(?Dealer $dealer = null): string
    {
        $products = Product::with('variants')
            ->where('is_active', true)
            ->where(function ($query) {
                $query->where(function ($products) {
                    $products->where('has_variants', false)->where('stock', '>', 0);
                })->orWhere(function ($products) {
                    $products->where('has_variants', true)
                        ->whereHas('variants', fn ($variants) => $variants->where('stock', '>', 0));
                });
            })
            ->orderBy('id')
            ->get();

        $pricing = app(PricingService::class);
        $dealerMargin = $dealer
            ? (float) ($dealer->default_marketplace_margin ?? $pricing->defaultMarketplaceMargin())
            : null;

        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><products></products>');

        foreach ($products as $product) {
            $node = $xml->addChild('product');
            $node->addChild('title', htmlspecialchars($product->title ?? ''));
            $node->addChild('stock_code', htmlspecialchars($product->stock_code ?? ''));
            $node->addChild('barcode', htmlspecialchars($product->barcode ?? ''));
            $node->addChild('brand', htmlspecialchars($product->brand ?? ''));
            $node->addChild('category', htmlspecialchars($product->category_path ?? $product->main_category ?? ''));
            $node->addChild('main_category', htmlspecialchars($product->main_category ?? ''));
            $node->addChild('sub_category', htmlspecialchars($product->sub_category ?? ''));
            $node->addChild('stock', (string) $product->effective_stock);

            $salePrice = (float) ($product->sell_price ?? $product->price ?? 0);
            $node->addChild('sale_price', number_format($salePrice, 2, '.', ''));
            $node->addChild('cost_price', number_format((float) ($product->cost_price ?? $salePrice), 2, '.', ''));

            // Bayiye özel önerilen perakende (bayi karı uygulanmış)
            if ($dealerMargin !== null) {
                $retail = $pricing->calculateDealerRetailPrice($salePrice, $dealerMargin);
                $node->addChild('retail_price', number_format($retail, 2, '.', ''));
                $node->addChild('dealer_margin_percent', number_format($dealerMargin, 2, '.', ''));
            }

            $node->addChild('currency', 'TL');
            $node->addChild('tax', (string) ($product->tax_rate ?? 20));
            $node->addChild('desi', number_format((float) ($product->desi ?? 0), 2, '.', ''));
            $node->addChild('description', htmlspecialchars($product->description ?? ''));

            $images = $product->images ?? [];
            $node->addChild('image_count', (string) count($images));
            foreach ($images as $i => $img) {
                $node->addChild('image_'.($i + 1), htmlspecialchars((string) $img));
            }

            if ($product->has_variants && $product->variants->count() > 0) {
                $variantsNode = $node->addChild('variants');
                foreach ($product->variants as $variant) {
                    if ((int) $variant->stock <= 0) {
                        continue;
                    }
                    $v = $variantsNode->addChild('variant');
                    $v->addChild('barcode', htmlspecialchars($variant->barcode ?? ''));
                    $v->addChild('stock', (string) $variant->stock);
                    $v->addChild('name', htmlspecialchars($variant->name ?? ''));
                    $v->addChild('value', htmlspecialchars($variant->value ?? ''));
                    if (isset($variant->price) && $variant->price !== null) {
                        $v->addChild('price', number_format((float) $variant->price, 2, '.', ''));
                    }
                }
            }
        }

        return $xml->asXML() ?: '<?xml version="1.0"?><products/>';
    }
}
