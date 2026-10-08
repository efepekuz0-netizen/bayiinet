<?php

namespace App\Jobs;

use App\Models\Dealer;
use App\Models\DealerTrendyolListing;
use App\Services\DealerTrendyolService;
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

        $listings = DealerTrendyolListing::query()
            ->where('dealer_id', $dealer->id)
            ->where('batch_request_id', $this->batchRequestId)
            ->where('status', 'sent')
            ->get();

        if ($listings->isEmpty()) {
            return;
        }

        $map = [];
        foreach ($listings as $l) {
            $map[(string) $l->barcode] = $l->id;
        }

        try {
            $connection = $trendyol->connection($dealer);
            // public recheck via checkBatch
            $result = $trendyol->checkBatch($dealer, $this->batchRequestId);
            Log::info('VerifyTrendyolBatch', [
                'dealer_id' => $this->dealerId,
                'batch' => $this->batchRequestId,
                'result' => $result,
            ]);
            // Hâlâ sent kalan varsa tekrar dene
            $still = DealerTrendyolListing::query()
                ->where('dealer_id', $dealer->id)
                ->where('batch_request_id', $this->batchRequestId)
                ->where('status', 'sent')
                ->exists();
            if ($still) {
                $this->release(30);
            }
        } catch (Throwable $e) {
            Log::warning('VerifyTrendyolBatch failed', ['error' => $e->getMessage()]);
            $this->release(45);
        }
    }
}
