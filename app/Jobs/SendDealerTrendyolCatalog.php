<?php

namespace App\Jobs;

use App\Models\Dealer;
use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Orchestrator: ürün id'lerini toplar, küçük batch job'lara böler, hemen biter.
 * Asıl API işi SendDealerTrendyolBatch'te.
 */
class SendDealerTrendyolCatalog implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 120;

    public int $tries = 1;

    public int $maxExceptions = 1;

    public int $uniqueFor = 120;

    public const BATCH_SIZE = 40;

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
        $this->onConnection(config('queue.default', 'database'));
        $this->onQueue('marketplace');
    }

    public function uniqueId(): string
    {
        return 'trendyol-send-'.$this->dealerId;
    }

    public function handle(): void
    {
        $dealer = Dealer::query()->find($this->dealerId);
        if (! $dealer || ! $dealer->hasTrendyolCredentials()) {
            Cache::put('trendyol_send_status_'.$this->dealerId, [
                'status' => 'error',
                'message' => 'Bayi veya Trendyol API bilgisi yok.',
                'finished_at' => now()->toIso8601String(),
            ], now()->addHours(6));

            return;
        }

        try {
            $query = Product::query()
                ->where('is_active', true)
                ->where(function ($q) {
                    $q->where(function ($plain) {
                        $plain->where('has_variants', false)->where('stock', '>', 0);
                    })->orWhere(function ($v) {
                        $v->where('has_variants', true)
                            ->whereHas('variants', fn ($s) => $s->where('stock', '>', 0));
                    });
                })
                ->orderBy('id');

            if ($this->productIds !== null) {
                $query->whereIn('id', $this->productIds);
            }

            if ($this->onlyMissing) {
                $listed = $dealer->trendyolListings()
                    ->whereIn('status', ['sent', 'created', 'pending'])
                    ->pluck('product_id')
                    ->unique()
                    ->filter()
                    ->all();
                if ($listed !== []) {
                    $query->whereNotIn('id', $listed);
                }
            }

            $ids = $query->pluck('id')->all();
            $total = count($ids);

            if ($total === 0) {
                Cache::put('trendyol_send_status_'.$dealer->id, [
                    'status' => 'done',
                    'sent' => 0,
                    'failed' => 0,
                    'processed' => 0,
                    'total' => 0,
                    'message' => 'Gönderilecek ürün yok.',
                    'finished_at' => now()->toIso8601String(),
                ], now()->addHours(12));

                return;
            }

            $chunks = array_chunk($ids, self::BATCH_SIZE);
            $totalBatches = count($chunks);

            Cache::put('trendyol_send_status_'.$dealer->id, [
                'status' => 'running',
                'sent' => 0,
                'failed' => 0,
                'processed' => 0,
                'total' => $total,
                'batches_done' => 0,
                'total_batches' => $totalBatches,
                'errors' => [],
                'batch_ids' => [],
                'message' => "0 / {$total} kuyruğa alındı ({$totalBatches} parça)…",
                'started_at' => now()->toIso8601String(),
            ], now()->addHours(12));

            foreach ($chunks as $i => $chunk) {
                SendDealerTrendyolBatch::dispatch(
                    $dealer->id,
                    array_values($chunk),
                    $this->categoryId,
                    $this->brandId,
                    $this->attributes,
                    $i,
                    $totalBatches,
                );  // delay yok — worker sırayla işler
            }

            Log::info('SendDealerTrendyolCatalog dispatched batches', [
                'dealer_id' => $dealer->id,
                'total' => $total,
                'batches' => $totalBatches,
            ]);
        } catch (Throwable $e) {
            Cache::put('trendyol_send_status_'.$this->dealerId, [
                'status' => 'error',
                'message' => $e->getMessage(),
                'finished_at' => now()->toIso8601String(),
            ], now()->addHours(6));
            Log::error('SendDealerTrendyolCatalog orchestrator failed', [
                'dealer_id' => $this->dealerId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function failed(?Throwable $exception): void
    {
        Cache::put('trendyol_send_status_'.$this->dealerId, [
            'status' => 'error',
            'message' => $exception?->getMessage() ?? 'Orchestrator başarısız',
            'finished_at' => now()->toIso8601String(),
        ], now()->addHours(6));
    }
}
