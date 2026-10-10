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

    /** İşçi timeout'undan kısa olmalı (worker 600s). */
    public int $timeout = 240;

    /** Yeniden deneme yok — hata progress'e yazılır, kuyruk kilidi olmaz. */
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
        public int $batchIndex,
        public int $totalBatches,
    ) {
        $this->onConnection(config('queue.default', 'database'));
        $this->onQueue('marketplace');
    }

    public function handle(DealerTrendyolService $trendyol): void
    {
        if (TrendyolSendProgress::isCancelled($this->dealerId)) {
            TrendyolSendProgress::addBatch($this->dealerId, 0, 0, 0, [], ['Gönderim durdurulduğu için atlandı.']);

            return;
        }

        $processed = count($this->productIds);
        $dealer = Dealer::query()->find($this->dealerId);

        if (! $dealer || ! $dealer->hasTrendyolCredentials()) {
            TrendyolSendProgress::addBatch(
                $this->dealerId,
                $processed,
                0,
                $processed,
                [],
                ['Bayi bulunamadı veya Trendyol API bilgileri eksik.'],
            );

            return;
        }

        $updated = 0;
        $failed = 0;
        $errors = [];
        $batches = [];

        try {
            $result = $trendyol->send(
                $dealer,
                $this->productIds,
                $this->categoryId,
                $this->brandId,
                $this->attributes,
            );

            $updated = (int) ($result['updated'] ?? 0);
            $failed = (int) ($result['failed'] ?? 0);
            $errors = array_slice((array) ($result['errors'] ?? []), 0, 12);
            $batches = array_values((array) ($result['batches'] ?? []));

            // accepted ama henüz created değilse "failed" sanılmasın
            $accepted = (int) ($result['accepted'] ?? 0);
            $pending = (int) ($result['pending'] ?? 0);
            if ($accepted > 0 && $failed === $processed && $pending === 0) {
                // API kabul etti, doğrulama bekliyor
                $failed = max(0, $processed - $accepted - $updated);
            }
        } catch (Throwable $e) {
            $failed = $processed;
            $errors = [mb_substr($e->getMessage(), 0, 240)];
            Log::warning('SendDealerTrendyolBatch exception', [
                'dealer_id' => $this->dealerId,
                'batch' => $this->batchIndex,
                'error' => $e->getMessage(),
            ]);
        }

        try {
            TrendyolSendProgress::addBatch($this->dealerId, $processed, $updated, $failed, $batches, $errors);
        } catch (Throwable $e) {
            Log::error('SendDealerTrendyolBatch progress write failed', ['error' => $e->getMessage()]);
        }
    }

    public function failed(?Throwable $exception): void
    {
        try {
            TrendyolSendProgress::addBatch(
                $this->dealerId,
                count($this->productIds),
                0,
                count($this->productIds),
                [],
                [mb_substr($exception?->getMessage() ?? 'Batch başarısız (kuyruk)', 0, 200)],
            );
        } catch (Throwable) {
        }
    }
}
