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

/**
 * Küçük ürün paketi gönderir. Asla kuyruğu kilitlemez:
 * - kısa timeout
 * - exception fırlatmaz
 * - "attempted too many times" üretmez (tries yüksek, handle içinde yakalama)
 */
class SendDealerTrendyolBatch implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 90;

    public int $tries = 3;

    public int $maxExceptions = 3;

    public bool $failOnTimeout = false;

    public function backoff(): array
    {
        return [5, 15, 30];
    }

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

            $result = $trendyol->send(
                $dealer,
                $this->productIds,
                $this->categoryId,
                $this->brandId,
                $this->attributes,
            );

            $updated = (int) ($result['updated'] ?? 0);
            $failed = (int) ($result['failed'] ?? 0);
            $accepted = (int) ($result['accepted'] ?? 0);
            $pending = (int) ($result['pending'] ?? 0);
            $errors = array_values(array_slice((array) ($result['errors'] ?? []), 0, 15));
            $batches = array_values((array) ($result['batches'] ?? []));

            // API kabul ettiyse "hepsi hatalı" gösterme
            if ($accepted > 0 || $pending > 0) {
                $failed = max(0, $processed - $accepted - $updated - $pending);
            }

            TrendyolSendProgress::addBatch(
                $this->dealerId,
                $processed,
                $updated + $accepted,
                $failed,
                $batches,
                $errors,
            );
        } catch (Throwable $e) {
            Log::warning('SendDealerTrendyolBatch soft-fail', [
                'dealer_id' => $this->dealerId,
                'batch' => $this->batchIndex,
                'attempt' => $this->attempts(),
                'error' => $e->getMessage(),
            ]);

            // Son denemede progress'e yaz; ortada exception fırlatma → "attempted too many times" olmaz
            if ($this->attempts() >= $this->tries) {
                TrendyolSendProgress::addBatch(
                    $this->dealerId,
                    $processed,
                    0,
                    $processed,
                    [],
                    [mb_substr($e->getMessage(), 0, 220)],
                );

                return;
            }

            // Ara deneme: kısa bekle, yeniden dene
            $this->release(10);
        }
    }

    public function failed(?Throwable $exception): void
    {
        try {
            $msg = $exception?->getMessage() ?? 'Bilinmeyen kuyruk hatası';
            // Kullanıcıya anlamsız "attempted too many times" yerine net metin
            if (str_contains(mb_strtolower($msg), 'attempted too many')) {
                $msg = 'Bu parça zaman aşımına uğradı veya işçi yeniden başladı. Tekrar gönderim deneyin; daha küçük paketlerle devam edilecek.';
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
