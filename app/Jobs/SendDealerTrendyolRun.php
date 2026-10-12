<?php

namespace App\Jobs;

use App\Models\Dealer;
use App\Models\DealerTrendyolListing;
use App\Models\Product;
use App\Services\DealerTrendyolService;
use App\Services\TrendyolSendProgress;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Masaüstü tarzı gönderim: tek iş, 100'lük API paketleri, arada kuyruk gecikmesi yok.
 * XML/form marka+kategori bilindiğinde kullanılır.
 */
class SendDealerTrendyolRun implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 600;

    public int $tries = 1;

    public int $uniqueFor = 700;

    public bool $failOnTimeout = false;

    /** Trendyol createProducts üst sınırı 1000; masaüstü 100 kullanıyor */
    public const API_CHUNK = 100;

    /**
     * @param  array<int, int>|null  $productIds
     * @param  array<int, array<string, mixed>>  $attributes
     */
    public function __construct(
        public int $dealerId,
        public ?array $productIds = null,
        public ?int $categoryId = null,
        public ?int $brandId = null,
        public array $attributes = [],
        public bool $automated = false,
    ) {
        $this->onConnection('database');
        $this->onQueue('marketplace');
    }

    public function uniqueId(): string
    {
        return 'trendyol-run-'.$this->dealerId;
    }

    public function handle(DealerTrendyolService $trendyol): void
    {
        if (TrendyolSendProgress::isCancelled($this->dealerId)) {
            return;
        }

        $dealer = Dealer::query()->find($this->dealerId);
        if (! $dealer || ! $dealer->hasTrendyolCredentials()) {
            TrendyolSendProgress::fail($this->dealerId, 'Bayi veya Trendyol API bilgisi yok.');

            return;
        }

        try {
            $query = Product::query()
                ->where('is_active', true)
                ->where(function ($q) {
                    $q->where('stock', '>', 0)->orWhere('has_variants', true);
                })
                ->orderBy('id');

            if (is_array($this->productIds) && $this->productIds !== []) {
                $query->whereIn('id', $this->productIds);
            }

            $ids = $query->pluck('id')->map(fn ($id) => (int) $id)->all();

            if ($ids !== []) {
                // "Tamamı Trendyol'da" olan ürünleri atla.
                // ÖNEMLİ: varyantlı üründe yalnızca BİR varyant gönderilmişse
                // ürün atlanmamalı; eksik varyantların da gönderilmesi gerekir.
                $ids = array_values(array_diff($ids, $this->alreadyCoveredProductIds($ids)));
            }

            $total = count($ids);
            if ($total === 0) {
                if ($this->automated) {
                    Log::info('SendDealerTrendyolRun: gonderilecek yeni urun yok', [
                        'dealer_id' => $this->dealerId,
                    ]);

                    return;
                }

                TrendyolSendProgress::fail($this->dealerId, 'Gonderilecek yeni urun yok.');

                return;
            }

            $chunks = array_chunk($ids, self::API_CHUNK);
            $totalBatches = count($chunks);

            TrendyolSendProgress::start(
                $this->dealerId,
                $total,
                $totalBatches,
                "0 / {$total} · masaustu hiz (100'luk paket)"
            );

            $categoryId = $this->categoryId;
            $brandId = $this->brandId;

            // XML kaynaktan doldur
            if ((! $categoryId || $categoryId <= 0) || (! $brandId || $brandId <= 0)) {
                $sample = Product::query()->with('source')->whereIn('id', array_slice($ids, 0, 20))->get();
                foreach ($sample as $p) {
                    if ((! $brandId || $brandId <= 0) && (int) ($p->source?->default_trendyol_brand_id ?? 0) > 0) {
                        $brandId = (int) $p->source->default_trendyol_brand_id;
                    }
                    if ((! $categoryId || $categoryId <= 0) && (int) ($p->source?->default_trendyol_category_id ?? 0) > 0) {
                        $categoryId = (int) $p->source->default_trendyol_category_id;
                    }
                }
                if (! $brandId || $brandId <= 0) {
                    $brandId = (int) config('bayiinet.trendyol.fallback_brand_id', 2613880);
                }
            }

            foreach ($chunks as $index => $chunk) {
                if (TrendyolSendProgress::isCancelled($this->dealerId)) {
                    break;
                }

                try {
                    $result = $trendyol->sendFast(
                        $dealer,
                        $chunk,
                        $categoryId,
                        $brandId,
                        $this->attributes,
                    );
                    $sent = (int) ($result['accepted'] ?? 0) + (int) ($result['updated'] ?? 0);
                    $failed = (int) ($result['failed'] ?? 0);
                    $errors = array_slice((array) ($result['errors'] ?? []), 0, 8);
                    $batches = array_values((array) ($result['batches'] ?? []));
                    TrendyolSendProgress::addBatch(
                        $this->dealerId,
                        count($chunk),
                        $sent,
                        $failed,
                        $batches,
                        $errors,
                    );
                } catch (Throwable $e) {
                    Log::warning('SendDealerTrendyolRun chunk', [
                        'dealer_id' => $this->dealerId,
                        'chunk' => $index,
                        'error' => $e->getMessage(),
                    ]);
                    TrendyolSendProgress::addBatch(
                        $this->dealerId,
                        count($chunk),
                        0,
                        count($chunk),
                        [],
                        [mb_substr($e->getMessage(), 0, 200)],
                    );
                }

                // Masaüstü gibi kısa nefes — API rate limit
                if ($index < $totalBatches - 1) {
                    usleep(200000); // 0.2 sn
                }
            }

            if ($this->automated) {
                // Saatlik otomatik tur: panelde "gönderim sürüyor" kalmasın.
                TrendyolSendProgress::finish(
                    $this->dealerId,
                    "Otomatik tur bitti: {$total} urun tarandi."
                );
            }
        } catch (Throwable $e) {
            TrendyolSendProgress::fail($this->dealerId, $e->getMessage());
        }
    }

    /**
     * Gönderilecek yeni bir şeyi olmayan ürünler: varyantsızda barkod,
     * varyantlıda TÜM varyantlar zaten Trendyol'a iletilmiş ('created'/'sent').
     *
     * @param  array<int, int>  $ids
     * @return array<int, int>
     */
    private function alreadyCoveredProductIds(array $ids): array
    {
        $variantCovered = DB::table('dealer_trendyol_listings')
            ->where('dealer_id', $this->dealerId)
            ->whereIn('product_id', $ids)
            ->whereIn('status', ['created', 'sent'])
            ->whereNotNull('product_variant_id')
            ->groupBy('product_id')
            ->selectRaw('product_id, count(distinct product_variant_id) as covered')
            ->pluck('covered', 'product_id');

        $plainCovered = DB::table('dealer_trendyol_listings')
            ->where('dealer_id', $this->dealerId)
            ->whereIn('product_id', $ids)
            ->whereIn('status', ['created', 'sent'])
            ->whereNull('product_variant_id')
            ->distinct()
            ->pluck('product_id')
            ->all();

        $variantCounts = DB::table('product_variants')
            ->whereIn('product_id', $ids)
            ->groupBy('product_id')
            ->selectRaw('product_id, count(*) as total')
            ->pluck('total', 'product_id');

        $covered = [];
        foreach ($plainCovered as $productId) {
            $covered[(int) $productId] = true;
        }

        foreach ($variantCovered as $productId => $count) {
            $needed = (int) ($variantCounts[$productId] ?? 0);
            if ($needed > 0 && (int) $count >= $needed) {
                $covered[(int) $productId] = true;
            }
        }

        return array_map('intval', array_keys($covered));
    }

    public function failed(?Throwable $exception): void
    {
        TrendyolSendProgress::fail(
            $this->dealerId,
            $exception?->getMessage() ?? 'Trendyol hizli gonderim basarisiz'
        );
    }
}
