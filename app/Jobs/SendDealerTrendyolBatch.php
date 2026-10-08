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

/**
 * Tek seferde en fazla ~50 ürün gönderir; ilerlemeyi cache'te günceller.
 */
class SendDealerTrendyolBatch implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 240;

    public int $tries = 1;

    public int $maxExceptions = 1;

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
        $dealer = Dealer::query()->find($this->dealerId);
        if (! $dealer || ! $dealer->hasTrendyolCredentials()) {
            return;
        }

        $key = 'trendyol_send_status_'.$dealer->id;
        $status = Cache::get($key, []);
        if (($status['status'] ?? '') === 'cancelled') {
            return;
        }

        try {
            $result = $trendyol->send(
                $dealer,
                $this->productIds,
                $this->categoryId,
                $this->brandId,
                $this->attributes,
            );
            $sent = (int) ($result['sent'] ?? 0);
            $failed = (int) ($result['failed'] ?? 0);
            $errors = array_slice($result['errors'] ?? [], 0, 5);
            $batches = $result['batches'] ?? [];
        } catch (Throwable $e) {
            $sent = 0;
            $failed = count($this->productIds);
            $errors = [$e->getMessage()];
            $batches = [];
            Log::warning('SendDealerTrendyolBatch exception', [
                'dealer_id' => $this->dealerId,
                'batch' => $this->batchIndex,
                'error' => $e->getMessage(),
            ]);
        }

        $lock = Cache::lock('trendyol_send_lock_'.$dealer->id, 30);
        try {
            $lock->block(20);
            $status = Cache::get($key, []);
            $status['sent'] = (int) ($status['sent'] ?? 0) + $sent;
            $status['failed'] = (int) ($status['failed'] ?? 0) + $failed;
            $status['processed'] = (int) ($status['processed'] ?? 0) + count($this->productIds);
            $status['batches_done'] = (int) ($status['batches_done'] ?? 0) + 1;
            $status['errors'] = array_slice(array_values(array_unique(array_merge(
                $status['errors'] ?? [],
                $errors
            ))), 0, 25);
            $status['batch_ids'] = array_slice(array_values(array_unique(array_merge(
                $status['batch_ids'] ?? [],
                $batches
            ))), -20);
            $total = (int) ($status['total'] ?? 0);
            $processed = (int) $status['processed'];
            $status['status'] = 'running';
            $status['message'] = "{$processed} / {$total} işlendi · gönderilen: {$status['sent']} · hatalı: {$status['failed']}";
            $status['updated_at'] = now()->toIso8601String();

            if ((int) ($status['batches_done'] ?? 0) >= $this->totalBatches) {
                $status['status'] = 'done';
                $status['message'] = "{$status['sent']} gönderildi, {$status['failed']} hatalı / toplam {$total}";
                $status['finished_at'] = now()->toIso8601String();
                if (! empty($status['started_at'])) {
                    try {
                        $status['seconds'] = now()->diffInSeconds(\Carbon\Carbon::parse($status['started_at']));
                    } catch (Throwable) {
                    }
                }
            }

            Cache::put($key, $status, now()->addHours(12));
        } finally {
            optional($lock)->release();
        }
    }

    public function failed(?Throwable $exception): void
    {
        $key = 'trendyol_send_status_'.$this->dealerId;
        $lock = Cache::lock('trendyol_send_lock_'.$this->dealerId, 15);
        try {
            $lock->block(10);
            $status = Cache::get($key, []);
            $status['failed'] = (int) ($status['failed'] ?? 0) + count($this->productIds);
            $status['processed'] = (int) ($status['processed'] ?? 0) + count($this->productIds);
            $status['batches_done'] = (int) ($status['batches_done'] ?? 0) + 1;
            $status['errors'] = array_slice(array_values(array_unique(array_merge(
                $status['errors'] ?? [],
                [$exception?->getMessage() ?? 'batch failed']
            ))), 0, 25);
            $status['message'] = 'Parça hata: '.($exception?->getMessage() ?? 'bilinmeyen');
            if ((int) ($status['batches_done'] ?? 0) >= $this->totalBatches) {
                $status['status'] = 'done';
                $status['finished_at'] = now()->toIso8601String();
            }
            Cache::put($key, $status, now()->addHours(12));
        } catch (Throwable) {
        } finally {
            optional($lock)->release();
        }
    }
}
