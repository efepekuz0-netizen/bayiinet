<?php

namespace App\Jobs;

use App\Models\Dealer;
use App\Models\Product;
use App\Services\TrendyolSendProgress;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Orchestrator: ürün id'lerini toplar, küçük batch job'lara böler, hemen biter.
 * Asıl API işi SendDealerTrendyolBatch'te.
 */
class SendDealerTrendyolCatalog implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 180;

    public int $tries = 1;

    public int $maxExceptions = 1;

    // Kuyruk kilitli kalırsa (işçi çökmesi vb.) en fazla 10 dk beklenir.
    public int $uniqueFor = 600;

    public const DEFAULT_BATCH_SIZE = 25;

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
        public bool $onlyMissing = false,
    ) {
        $this->onConnection(config('queue.default', 'database'));
        $this->onQueue('marketplace');
    }

    public function uniqueId(): string
    {
        return 'trendyol-send-'.$this->dealerId;
    }

    public function handle(): void
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
                    $q->where('stock', '>', 0)
                        ->orWhere('has_variants', true);
                })
                ->orderBy('id');

            if ($this->productIds !== null && $this->productIds !== []) {
                $query->whereIn('id', $this->productIds);
            }

            if ($this->onlyMissing) {
                // Alt sorgu kullanılır: on binlerce id PHP'ye çekilmez.
                $query->whereNotIn('id', function ($sub) use ($dealer): void {
                    $sub->select('product_id')
                        ->from('dealer_trendyol_listings')
                        ->where('dealer_id', $dealer->id)
                        ->whereIn('status', ['sent', 'created', 'pending'])
                        ->whereNotNull('product_id');
                });
            }

            $ids = $query->pluck('id')->all();
            $total = count($ids);

            if ($total === 0) {
                TrendyolSendProgress::finish(
                    $dealer->id,
                    $this->onlyMissing
                        ? 'Gönderilecek yeni ürün yok — tüm stoklu ürünler zaten Trendyol’a gönderilmiş.'
                        : 'Gönderilecek stoklu ürün bulunamadı.'
                );

                return;
            }

            $batchSize = max(1, (int) config('bayiinet.trendyol.batch_size', self::DEFAULT_BATCH_SIZE));
            $chunks = array_chunk($ids, $batchSize);
            $totalBatches = count($chunks);

            TrendyolSendProgress::start($dealer->id, $total, $totalBatches);

            foreach ($chunks as $i => $chunk) {
                SendDealerTrendyolBatch::dispatch(
                    $dealer->id,
                    array_values($chunk),
                    $this->categoryId,
                    $this->brandId,
                    $this->attributes,
                    $i,
                    $totalBatches,
                );  // delay yok — worker sırayla işler
            }

            Log::info('SendDealerTrendyolCatalog dispatched batches', [
                'dealer_id' => $dealer->id,
                'total' => $total,
                'batches' => $totalBatches,
                'batch_size' => $batchSize,
            ]);
        } catch (Throwable $e) {
            TrendyolSendProgress::fail($this->dealerId, 'Gönderim başlatılamadı: '.$e->getMessage());
            Log::error('SendDealerTrendyolCatalog orchestrator failed', [
                'dealer_id' => $dealer->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function failed(?Throwable $exception): void
    {
        TrendyolSendProgress::fail($this->dealerId, 'Gönderim başlatılamadı: '.($exception?->getMessage() ?? 'Orchestrator başarısız'));
    }
}
