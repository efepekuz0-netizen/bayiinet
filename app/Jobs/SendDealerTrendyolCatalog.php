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
 * Orchestrator: ürün id listesini toplar, küçük batch job'lara böler, hemen biter.
 */
class SendDealerTrendyolCatalog implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 120;

    public int $tries = 2;

    public int $maxExceptions = 2;

    public int $uniqueFor = 300;

    public bool $failOnTimeout = false;

    /** Render tek işçi için güvenli paket boyutu */
    public const DEFAULT_BATCH_SIZE = 10;

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
        $this->onConnection('database');
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
                    $q->where('stock', '>', 0)->orWhere('has_variants', true);
                })
                ->orderBy('id');

            if (is_array($this->productIds) && $this->productIds !== []) {
                $query->whereIn('id', $this->productIds);
            }

            $ids = $query->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
            $total = count($ids);

            if ($total === 0) {
                TrendyolSendProgress::fail($this->dealerId, 'Gönderilecek stoklu ürün bulunamadı.');

                return;
            }

            $batchSize = max(5, min(15, (int) config('bayiinet.trendyol.batch_size', self::DEFAULT_BATCH_SIZE)));
            $chunks = array_chunk($ids, $batchSize);
            $totalBatches = count($chunks);

            TrendyolSendProgress::start(
                $this->dealerId,
                $total,
                $totalBatches,
                "0 / {$total} kuyruğa alındı · {$totalBatches} parça"
            );

            foreach ($chunks as $index => $chunk) {
                if (TrendyolSendProgress::isCancelled($this->dealerId)) {
                    break;
                }

                // İşçiyi boğmamak için parçalar arası gecikme
                SendDealerTrendyolBatch::dispatch(
                    $this->dealerId,
                    $chunk,
                    $this->categoryId,
                    $this->brandId,
                    $this->attributes,
                    $index + 1,
                    $totalBatches,
                )->delay(now()->addSeconds($index * 3));
            }

            Log::info('SendDealerTrendyolCatalog dispatched', [
                'dealer_id' => $dealer->id,
                'total' => $total,
                'batches' => $totalBatches,
                'batch_size' => $batchSize,
            ]);
        } catch (Throwable $e) {
            TrendyolSendProgress::fail($this->dealerId, 'Gönderim başlatılamadı: '.$e->getMessage());
            Log::error('SendDealerTrendyolCatalog failed', [
                'dealer_id' => $this->dealerId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function failed(?Throwable $exception): void
    {
        $msg = $exception?->getMessage() ?? 'Orchestrator başarısız';
        if (str_contains(mb_strtolower($msg), 'attempted too many')) {
            $msg = 'Gönderim orchestrator zaman aşımı. Tekrar deneyin.';
        }
        TrendyolSendProgress::fail($this->dealerId, $msg);
    }
}
