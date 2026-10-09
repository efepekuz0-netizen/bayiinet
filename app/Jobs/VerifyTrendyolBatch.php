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

class VerifyTrendyolBatch implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    public int $timeout = 120;

    public function __construct(
        public int $dealerId,
        public string $batchRequestId,
    ) {
        $this->onConnection(config('queue.default', 'database'));
        $this->onQueue('marketplace');
    }

    public function backoff(): array
    {
        return [15, 30, 60, 90];
    }

    public function handle(DealerTrendyolService $trendyol): void
    {
        $dealer = Dealer::query()->find($this->dealerId);
        if (! $dealer || ! $dealer->hasTrendyolCredentials()) {
            return;
        }

        try {
            // Trendyol'un batch sonucunu oku; listing durumlarını günceller
            $result = $trendyol->checkBatch($dealer, $this->batchRequestId);

            Log::info('VerifyTrendyolBatch', [
                'dealer_id' => $this->dealerId,
                'batch' => $this->batchRequestId,
                'result' => $result,
            ]);
        } catch (Throwable $e) {
            Log::warning('VerifyTrendyolBatch failed', [
                'dealer_id' => $this->dealerId,
                'batch' => $this->batchRequestId,
                'error' => $e->getMessage(),
            ]);
            $this->release(45);

            return;
        }

        // Sonuçları gönderim ekranına yansıt (idempotent)
        $resolved = TrendyolSendProgress::resolveBatch($this->dealerId, $this->batchRequestId);

        // Hâlâ sonuçlanmamış kalem varsa bir kez daha dene
        if (($resolved['pending'] ?? 0) > 0 && $this->attempts() < $this->tries) {
            $this->release(30);
        }
    }
}
