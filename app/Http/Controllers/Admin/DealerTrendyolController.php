<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Dealer;
use App\Models\Product;
use App\Jobs\DeleteDealerTrendyolProducts;
use App\Jobs\SendDealerTrendyolCatalog;
use App\Models\Source;
use App\Services\DealerTrendyolService;
use App\Services\AdminAudit;
use App\Services\TrendyolSendProgress;
use Illuminate\Support\Facades\Cache;
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

        $sendStatus = TrendyolSendProgress::get($dealer->id);
        $deleteStatus = Cache::get('trendyol_delete_status_'.$dealer->id);
        $sources = Source::query()->orderBy('name')->get(['id', 'name']);

        return view('admin.dealers.trendyol', compact(
            'dealer', 'products', 'prices', 'margin', 'listed', 'counts', 'recent', 'batches', 'search',
            'defaultCategoryId', 'defaultBrandId', 'sendStatus', 'deleteStatus', 'sources',
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
            'product_ids' => 'nullable|array',
            'product_ids.*' => 'integer|exists:products,id',
            'send_all' => 'nullable|boolean',
            'category_id' => 'nullable|integer|min:1',
            'brand_id' => 'nullable|integer|min:1',
            'attributes_json' => 'nullable|string|max:20000',
            'remember_category' => 'nullable|boolean',
        ]);

        if (! $dealer->isActive()) {
            return $this->back($dealer)->with('error', 'Bayi onaylı (aktif) değil.');
        }

        $sendAll = $request->boolean('send_all');
        if ($sendAll) {
            // Limitsiz — job tüm stoklu aktif ürünleri alır
            $productIds = null;
            $countHint = Product::query()
                ->where('is_active', true)
                ->where(function ($q) {
                    $q->where(function ($plain) {
                        $plain->where('has_variants', false)->where('stock', '>', 0);
                    })->orWhere(function ($v) {
                        $v->where('has_variants', true)
                            ->whereHas('variants', fn ($s) => $s->where('stock', '>', 0));
                    });
                })
                ->count();
            if ($countHint === 0) {
                return $this->back($dealer)->with('error', 'Gönderilecek stoklu ürün yok.');
            }
        } else {
            $productIds = array_map('intval', $data['product_ids'] ?? []);
            if ($productIds === []) {
                return $this->back($dealer)->with('error', 'Gönderilecek ürün seçilmedi. Tümünü gönder veya listeden seçin.');
            }
            $countHint = count($productIds);
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

        // Verilen kategori/markayı varsayılan olarak hatırla (otomatik eşleme yedegi)
        if ($categoryId > 0 || $brandId > 0) {
            $matcher->setFallback($categoryId, $brandId);
        }

        if (! $dealer->hasTrendyolCredentials()) {
            return $this->back($dealer)->with('error', 'Önce Trendyol API bilgilerini kaydedin.');
        }

        if (TrendyolSendProgress::isActive($dealer->id)) {
            return $this->back($dealer)->with(
                'error',
                'Bu bayi için gönderim zaten devam ediyor: '.(TrendyolSendProgress::get($dealer->id)['message'] ?? 'çalışıyor').' Önce «Gönderimi durdur» butonuna basın.'
            );
        }

        // Önceki gönderimden kalan benzersiz iş kilidini serbest bırak (en iyi çaba)
        try {
            Cache::lock('laravel_unique_job:trendyol-send-'.$dealer->id)->forceRelease();
        } catch (\Throwable) {
        }

        // Sayfa anında dönsün — gönderim kuyrukta
        TrendyolSendProgress::queued($dealer->id, $countHint.' ürün kuyruğa alındı…');

        SendDealerTrendyolCatalog::dispatch(
            $dealer->id,
            $sendAll ? null : $productIds,
            $categoryId > 0 ? $categoryId : null,
            $brandId > 0 ? $brandId : null,
            $attributes,
            false,
        );

        $n = $sendAll ? 'tüm stoklu ürünler' : (count($productIds).' ürün');

        AdminAudit::log('trendyol.send', $dealer->company_name.' için '.$n.' Trendyol gönderim kuyruğuna alındı.', [
            'dealer_id' => $dealer->id,
            'send_all' => $sendAll,
            'product_ids' => $sendAll ? null : $productIds,
            'category_id' => $categoryId > 0 ? $categoryId : null,
            'brand_id' => $brandId > 0 ? $brandId : null,
        ]);

        return $this->back($dealer)->with(
            'success',
            $n.' Trendyol kuyruğuna alındı. İşlem arka planda sürer; birkaç dakika sonra Sonuç sorgula veya durum kutusunu kontrol edin.'
        );
    }



    public function cancelSend(Dealer $dealer): RedirectResponse
    {
        // Kuyruktaki batch işleri durumu her adımda kontrol eder; iptal bayrağını
        // gördüklerinde Trendyol'a hiç dokunmadan kendilerini atlarlar.
        // (Eskiden marketplace kuyruğu toptan siliniyordu; diğer bayilerin
        // bekleyen işleri de kayboluyordu.)
        TrendyolSendProgress::cancel(
            $dealer->id,
            'Gönderim durduruldu. Kuyruktaki işler atlanıyor; yeni gönderim başlatabilirsiniz.'
        );

        // Benzersiz iş kilidi serbest (en iyi çaba)
        try {
            Cache::lock('laravel_unique_job:trendyol-send-'.$dealer->id)->forceRelease();
        } catch (\Throwable) {
        }

        return $this->back($dealer)->with('success', 'Trendyol gönderimi durduruldu. Kuyrukta bekleyen işler atlanacak.');
    }

    public function deleteProducts(Request $request, Dealer $dealer): RedirectResponse
    {
        if (! $dealer->hasTrendyolCredentials()) {
            return $this->back($dealer)->with('error', 'Trendyol API bilgileri eksik.');
        }

        $data = $request->validate([
            'scope' => 'required|string',
            'confirm' => 'required|accepted',
        ]);

        $scope = $data['scope'];
        if ($scope === 'all') {
            $jobScope = 'all';
            $label = 'tüm Trendyol ürünleri';
        } elseif (str_starts_with($scope, 'source:')) {
            $sourceId = (int) substr($scope, 7);
            if (! Source::query()->whereKey($sourceId)->exists()) {
                return $this->back($dealer)->with('error', 'XML kaynağı bulunamadı.');
            }
            $jobScope = $sourceId;
            $src = Source::query()->find($sourceId);
            $label = '«'.($src->name ?? $sourceId).'» kaynağı ürünleri';
        } else {
            return $this->back($dealer)->with('error', 'Geçersiz silme kapsamı.');
        }

        Cache::put('trendyol_delete_status_'.$dealer->id, [
            'status' => 'queued',
            'message' => $label.' silme kuyruğunda…',
        ], now()->addHours(2));

        DeleteDealerTrendyolProducts::dispatch($dealer->id, $jobScope);

        // Toplu silme geri alınamaz: denetim izine yaz
        AdminAudit::log('trendyol.delete', $dealer->company_name.' için '.$label." Trendyol'dan silinmek üzere kuyruğa alındı.", [
            'dealer_id' => $dealer->id,
            'scope' => $jobScope,
        ]);

        return $this->back($dealer)->with('success', $label.' Trendyol silme kuyruğuna alındı.');
    }


    public function recheckBatches(Dealer $dealer): RedirectResponse
    {
        try {
            $result = $this->trendyol->recheckSentBatches($dealer);
        } catch (\LogicException $e) {
            return $this->back($dealer)->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);
            return $this->back($dealer)->with('error', 'Kontrol başarısız: '.$e->getMessage());
        }

        $msg = "{$result['batches']} batch kontrol: {$result['created']} oluştu, {$result['failed']} hatalı, {$result['pending']} bekliyor.";
        if (! empty($result['errors'])) {
            $msg .= ' Örnek: '.implode(' | ', array_slice($result['errors'], 0, 3));
        }

        return $this->back($dealer)->with(
            ($result['created'] > 0 || $result['failed'] > 0) ? 'success' : 'error',
            $msg
        );
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
