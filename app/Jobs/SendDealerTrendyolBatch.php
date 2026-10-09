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

    public int $timeout = 300;

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
        // "Gönderimi durdur"a basıldıysa kuyruktaki işler kendini atlar.
        // (Eskiden tüm kuyruk siliniyordu; diğer bayilerin işleri de gidiyordu.)
        if (TrendyolSendProgress::isCancelled($this->dealerId)) {
            // Parça "işlendi" sayılır, böylece gönderim durumu takılı kalmaz.
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

            // 'created' ve 'pending' sayaçları batch bazlı tutulur
            // (SendDealerTrendyolCatalog registerBatch ile kaydeder),
            // burada yalnızca batch'e bağlı olmayan sonuçlar aktarılır.
            $updated = (int) ($result['updated'] ?? 0);
            $failed = (int) ($result['failed'] ?? 0);
            $errors = array_slice((array) ($result['errors'] ?? []), 0, 8);
            $batches = array_values((array) ($result['batches'] ?? []));
        } catch (Throwable $e) {
            $failed = $processed;
            $errors = [mb_substr($e->getMessage(), 0, 200)];
            Log::warning('SendDealerTrendyolBatch exception', [
                'dealer_id' => $this->dealerId,
                'batch' => $this->batchIndex,
                'error' => $e->getMessage(),
            ]);
        }

        // Asla exception fırlatma — "attempted too many times" olmasın
        TrendyolSendProgress::addBatch($this->dealerId, $processed, $updated, $failed, $batches, $errors);
    }

    public function failed(?Throwable $exception): void
    {
        TrendyolSendProgress::addBatch(
            $this->dealerId,
            count($this->productIds),
            0,
            count($this->productIds),
            [],
            [mb_substr($exception?->getMessage() ?? 'batch failed', 0, 200)],
        );
    }
}
