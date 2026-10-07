<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Dealer;
use App\Models\Product;
use App\Services\DealerTrendyolService;
use App\Services\TrendyolCategoryMatcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use LogicException;
use Throwable;

class DealerTrendyolController extends Controller
{
    public function __construct(private readonly DealerTrendyolService $trendyol) {}

    public function index(Request $request, Dealer $dealer)
    {
        $search = trim((string) $request->get('q', ''));

        $products = Product::query()
            ->where('is_active', true)
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('title', 'like', "%{$search}%")
                        ->orWhere('stock_code', 'like', "%{$search}%")
                        ->orWhere('barcode', 'like', "%{$search}%");
                });
            })
            ->orderBy('title')
            ->paginate(30)
            ->withQueryString();

        $margin = $this->trendyol->dealerMargin($dealer);
        $prices = [];
        foreach ($products as $product) {
            $prices[$product->id] = $this->trendyol->salePrice($product, null, $margin);
        }

        $listed = $dealer->trendyolListings()->pluck('status', 'product_id');
        $counts = $dealer->trendyolListings()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');
        $recent = $dealer->trendyolListings()
            ->with('product:id,title,stock_code')
            ->latest('updated_at')
            ->take(50)
            ->get();
        $batches = $dealer->trendyolListings()
            ->whereNotNull('batch_request_id')
            ->latest('sent_at')
            ->pluck('batch_request_id')
            ->unique()
            ->take(10);

        $catMap = app(\App\Services\TrendyolCategoryMatcher::class)->map();
        $defaultCategoryId = (int) ($catMap['fallback_id'] ?? 0) ?: old('category_id');
        $defaultBrandId = (int) ($catMap['fallback_brand_id'] ?? 0) ?: old('brand_id');

        return view('admin.dealers.trendyol', compact(
            'dealer', 'products', 'prices', 'margin', 'listed', 'counts', 'recent', 'batches', 'search',
            'defaultCategoryId', 'defaultBrandId',
        ));
    }

    public function saveConnection(Request $request, Dealer $dealer): RedirectResponse
    {
        $data = $request->validate([
            'trendyol_seller_id' => 'required|string|max:30',
            'api_key' => 'nullable|string|max:255',
            'api_secret' => 'nullable|string|max:255',
            'default_marketplace_margin' => 'required|numeric|min:0|max:500',
        ]);

        $existing = $dealer->trendyol_credentials ?? [];
        $apiKey = trim((string) ($data['api_key'] ?? '')) ?: ($existing['api_key'] ?? null);
        $apiSecret = trim((string) ($data['api_secret'] ?? '')) ?: ($existing['api_secret'] ?? null);

        if (! $apiKey || ! $apiSecret) {
            return $this->back($dealer)->with('error', 'API Key ve API Secret girilmeli.');
        }

        $dealer->update([
            'trendyol_seller_id' => trim($data['trendyol_seller_id']),
            'trendyol_credentials' => ['api_key' => $apiKey, 'api_secret' => $apiSecret],
            'default_marketplace_margin' => $data['default_marketplace_margin'],
            'trendyol_last_error' => null,
        ]);

        return $this->runTest($dealer->fresh(), 'Bilgiler kaydedildi ve Trendyol bağlantısı başarılı.');
    }

    public function test(Dealer $dealer): RedirectResponse
    {
        return $this->runTest($dealer, 'Trendyol bağlantısı başarılı.');
    }


    public function send(Request $request, Dealer $dealer, TrendyolCategoryMatcher $matcher): RedirectResponse
    {
        $data = $request->validate([
            'product_ids' => 'nullable|array|max:5000',
            'product_ids.*' => 'integer|exists:products,id',
            'send_all' => 'nullable|boolean',
            'category_id' => 'required|integer|min:1',
            'brand_id' => 'required|integer|min:1',
            'attributes_json' => 'nullable|string|max:20000',
            'remember_category' => 'nullable|boolean',
        ]);

        if (! $dealer->isActive()) {
            return $this->back($dealer)->with('error', 'Bayi onaylı (aktif) değil.');
        }

        $sendAll = $request->boolean('send_all');
        if ($sendAll) {
            $productIds = Product::query()
                ->where('is_active', true)
                ->where(function ($q) {
                    $q->where(function ($plain) {
                        $plain->where('has_variants', false)->where('stock', '>', 0);
                    })->orWhere(function ($v) {
                        $v->where('has_variants', true)
                            ->whereHas('variants', fn ($s) => $s->where('stock', '>', 0));
                    });
                })
                ->orderBy('id')
                ->limit(5000)
                ->pluck('id')
                ->all();
        } else {
            $productIds = array_map('intval', $data['product_ids'] ?? []);
        }

        if ($productIds === []) {
            return $this->back($dealer)->with('error', 'Gönderilecek ürün seçilmedi. Tümünü gönder veya listeden seçin.');
        }

        $attributes = [];
        $json = trim((string) ($data['attributes_json'] ?? ''));
        if ($json !== '') {
            $attributes = json_decode($json, true);
            $valid = is_array($attributes) && array_is_list($attributes)
                && collect($attributes)->every(fn ($row) => is_array($row));
            if (! $valid) {
                return $this->back($dealer)->withInput()->with('error', 'Özellikler alanı geçerli bir JSON listesi olmalı. Örnek: [{"attributeId":338,"attributeValueId":6980}]');
            }
        }

        $categoryId = (int) $data['category_id'];
        $brandId = (int) $data['brand_id'];

        // Masaüstü gibi: bu kategori/markayı varsayılan olarak hatırla
        if ($request->boolean('remember_category') || $sendAll) {
            $matcher->setFallback($categoryId, $brandId);
        }

        try {
            $result = $this->trendyol->send(
                $dealer,
                $productIds,
                $categoryId,
                $brandId,
                $attributes,
            );
        } catch (LogicException $e) {
            return $this->back($dealer)->with('error', $e->getMessage());
        } catch (Throwable $e) {
            report($e);

            return $this->back($dealer)->with('error', 'Gönderim sırasında hata oluştu: '.$e->getMessage());
        }

        $message = "{$result['sent']} ürün Trendyol'a gönderildi.";
        if ($result['sent'] > 0) {
            $message .= ' Sonucu birkaç dakika sonra "Sonuç sorgula" ile kontrol edin.';
        }
        if ($result['failed'] > 0) {
            $message .= " {$result['failed']} ürün gönderilemedi: ".implode(' / ', array_slice($result['errors'], 0, 3));
        }

        return $this->back($dealer)->with($result['sent'] > 0 ? 'success' : 'error', $message);
    }

    public function checkBatch(Request $request, Dealer $dealer): RedirectResponse
    {
        $data = $request->validate(['batch_request_id' => 'required|string|max:100']);

        try {
            $result = $this->trendyol->checkBatch($dealer, $data['batch_request_id']);
        } catch (LogicException $e) {
            return $this->back($dealer)->with('error', $e->getMessage());
        } catch (Throwable $e) {
            report($e);

            return $this->back($dealer)->with('error', 'Sonuç sorgulanamadı: '.$e->getMessage());
        }

        return $this->back($dealer)->with(
            'success',
            "Trendyol işlem durumu: {$result['status']}. Oluşan: {$result['created']}, hatalı: {$result['failed']}."
        );
    }

    public function syncInventory(Dealer $dealer): RedirectResponse
    {
        try {
            $result = $this->trendyol->syncInventory($dealer);
        } catch (LogicException $e) {
            return $this->back($dealer)->with('error', $e->getMessage());
        } catch (Throwable $e) {
            report($e);

            return $this->back($dealer)->with('error', 'Fiyat/stok güncellenemedi: '.$e->getMessage());
        }

        return $this->back($dealer)->with('success', "{$result['updated']} ürünün fiyat ve stoğu Trendyol'a gönderildi.");
    }

    private function runTest(Dealer $dealer, string $successMessage): RedirectResponse
    {
        try {
            $this->trendyol->testConnection($dealer);
            $dealer->update(['trendyol_last_error' => null]);

            return $this->back($dealer)->with('success', $successMessage);
        } catch (Throwable $e) {
            $dealer->update(['trendyol_last_error' => mb_substr($e->getMessage(), 0, 1000)]);

            return $this->back($dealer)->with('error', $e->getMessage());
        }
    }

    private function back(Dealer $dealer): RedirectResponse
    {
        return redirect()->route('admin.dealers.trendyol', $dealer);
    }
}
