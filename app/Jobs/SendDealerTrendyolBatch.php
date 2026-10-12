<?php

namespace App\Jobs;

use App\Models\Dealer;
use App\Services\DealerTrendyolService;
use App\Services\TrendyolSendProgress;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendDealerTrendyolBatch implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** Render worker için güvenli üst sınır */
    public int $timeout = 45;

    public int $tries = 1;

    public int $maxExceptions = 1;

    public bool $failOnTimeout = false;

    /**
     * @param  array<int, int>  $productIds
     * @param  array<int, array<string, mixed>>  $attributes
     */
    public function __construct(
        public int $dealerId,
        public array $productIds,
        public ?int $categoryId,
        public ?int $brandId,
        public array $attributes,
        public int $batchIndex = 1,
        public int $totalBatches = 1,
    ) {
        $this->onConnection('database');
        $this->onQueue('marketplace');
    }

    public function handle(DealerTrendyolService $trendyol): void
    {
        $processed = max(1, count($this->productIds));

        try {
            if (TrendyolSendProgress::isCancelled($this->dealerId)) {
                TrendyolSendProgress::addBatch($this->dealerId, 0, 0, 0, [], []);

                return;
            }

            $dealer = Dealer::query()->find($this->dealerId);
            if (! $dealer || ! $dealer->hasTrendyolCredentials()) {
                TrendyolSendProgress::addBatch(
                    $this->dealerId,
                    $processed,
                    0,
                    $processed,
                    [],
                    ['Bayi veya Trendyol API bilgisi eksik.']
                );

                return;
            }

            $result = $trendyol->sendFast(
                $dealer,
                $this->productIds,
                $this->categoryId,
                $this->brandId,
                $this->attributes,
            );

            $sent = (int) ($result['accepted'] ?? 0) + (int) ($result['updated'] ?? 0);
            $failed = (int) ($result['failed'] ?? 0);
            $errors = array_values(array_slice((array) ($result['errors'] ?? []), 0, 20));
            $batches = array_values((array) ($result['batches'] ?? []));

            TrendyolSendProgress::addBatch(
                $this->dealerId,
                $processed,
                $sent,
                $failed,
                $batches,
                $errors,
            );
        } catch (Throwable $e) {
            Log::warning('SendDealerTrendyolBatch', [
                'dealer_id' => $this->dealerId,
                'error' => $e->getMessage(),
            ]);
            TrendyolSendProgress::addBatch(
                $this->dealerId,
                $processed,
                0,
                $processed,
                [],
                [mb_substr($e->getMessage(), 0, 220)],
            );
        }
    }

    public function failed(?Throwable $exception): void
    {
        try {
            $msg = $exception?->getMessage() ?? 'Parça işlenemedi';
            if (str_contains(mb_strtolower($msg), 'attempted too many')) {
                $msg = 'Parça zaman aşımı veya işçi yeniden başladı. Kuyruğu sıfırlayıp tekrar gönderin; kategori+marka dolu olsun.';
            }
            TrendyolSendProgress::addBatch(
                $this->dealerId,
                count($this->productIds),
                0,
                count($this->productIds),
                [],
                [mb_substr($msg, 0, 220)],
            );
        } catch (Throwable) {
        }
    }
}
