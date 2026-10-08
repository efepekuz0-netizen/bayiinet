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

class SendDealerTrendyolBatch implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 180;

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
        $key = 'trendyol_send_status_'.$this->dealerId;
        $status = Cache::get($key, []);

        if (in_array($status['status'] ?? '', ['cancelled', 'stopped'], true)) {
            return;
        }

        $dealer = Dealer::query()->find($this->dealerId);
        if (! $dealer || ! $dealer->hasTrendyolCredentials()) {
            $this->bumpStatus($key, 0, count($this->productIds), ['Bayi/credentials yok']);

            return;
        }

        $sent = 0;
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
            $sent = (int) ($result['sent'] ?? 0);
            $failed = (int) ($result['failed'] ?? 0);
            $errors = array_slice($result['errors'] ?? [], 0, 8);
            $batches = $result['batches'] ?? [];
        } catch (Throwable $e) {
            $sent = 0;
            $failed = count($this->productIds);
            $errors = [mb_substr($e->getMessage(), 0, 200)];
            Log::warning('SendDealerTrendyolBatch exception', [
                'dealer_id' => $this->dealerId,
                'batch' => $this->batchIndex,
                'error' => $e->getMessage(),
            ]);
        }

        // Asla exception fırlatma — "attempted too many times" olmasın
        $this->bumpStatus($key, $sent, $failed, $errors, $batches);
    }

    public function failed(?Throwable $exception): void
    {
        $this->bumpStatus(
            'trendyol_send_status_'.$this->dealerId,
            0,
            count($this->productIds),
            [mb_substr($exception?->getMessage() ?? 'batch failed', 0, 200)]
        );
    }

    /** @param  list<string>  $errors */
    private function bumpStatus(string $key, int $sent, int $failed, array $errors = [], array $batches = []): void
    {
        try {
            $status = Cache::get($key, []);
            if (in_array($status['status'] ?? '', ['cancelled', 'stopped'], true)) {
                return;
            }

            $status['sent'] = (int) ($status['sent'] ?? 0) + $sent;
            $status['failed'] = (int) ($status['failed'] ?? 0) + $failed;
            $status['processed'] = (int) ($status['processed'] ?? 0) + count($this->productIds);
            $status['batches_done'] = (int) ($status['batches_done'] ?? 0) + 1;
            $status['total_batches'] = (int) ($status['total_batches'] ?? $this->totalBatches);
            $status['total'] = (int) ($status['total'] ?? 0);
            $status['errors'] = array_slice(array_values(array_unique(array_merge(
                $status['errors'] ?? [],
                $errors
            ))), 0, 20);
            $status['batch_ids'] = array_slice(array_values(array_unique(array_merge(
                $status['batch_ids'] ?? [],
                $batches
            ))), -30);
            $status['updated_at'] = now()->toIso8601String();

            $processed = (int) $status['processed'];
            $total = (int) $status['total'];
            $status['message'] = "{$processed} / {$total} işlendi · gönderilen: {$status['sent']} · hatalı: {$status['failed']}";
            $status['status'] = 'running';

            $done = (int) ($status['batches_done'] ?? 0) >= (int) ($status['total_batches'] ?? $this->totalBatches);
            if ($done || ($total > 0 && $processed >= $total)) {
                $status['status'] = 'done';
                $status['message'] = "Bitti: {$status['sent']} gönderildi, {$status['failed']} hatalı / {$total}";
                $status['finished_at'] = now()->toIso8601String();
            }

            Cache::put($key, $status, now()->addHours(12));
        } catch (Throwable $e) {
            Log::warning('bumpStatus failed', ['error' => $e->getMessage()]);
        }
    }
}
