<?php

namespace App\Jobs;

use App\Models\Dealer;
use App\Services\DealerTrendyolService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class DeleteDealerTrendyolProducts implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 840;

    public int $tries = 1;

    /** @param  'all'|int  $scope */
    public function __construct(
        public int $dealerId,
        public string|int $scope = 'all',
    ) {
        $this->onConnection(config('queue.default', 'database'));
        $this->onQueue('marketplace');
    }

    public function handle(DealerTrendyolService $trendyol): void
    {
        $dealer = Dealer::query()->find($this->dealerId);
        if (! $dealer || ! $dealer->hasTrendyolCredentials()) {
            return;
        }

        $key = 'trendyol_delete_status_'.$dealer->id;
        Cache::put($key, [
            'status' => 'running',
            'message' => 'Trendyol ürün silme başladı…',
            'started_at' => now()->toIso8601String(),
        ], now()->addHours(6));

        try {
            $result = $trendyol->deleteFromTrendyol($dealer, $this->scope);
            Cache::put($key, [
                'status' => 'done',
                'deleted' => $result['deleted'],
                'failed' => $result['failed'],
                'errors' => array_slice($result['errors'], 0, 10),
                'message' => "{$result['deleted']} silindi, {$result['failed']} hatalı",
                'finished_at' => now()->toIso8601String(),
            ], now()->addHours(12));
        } catch (Throwable $e) {
            Cache::put($key, [
                'status' => 'error',
                'message' => $e->getMessage(),
                'finished_at' => now()->toIso8601String(),
            ], now()->addHours(6));
            Log::error('DeleteDealerTrendyolProducts failed', [
                'dealer_id' => $this->dealerId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
