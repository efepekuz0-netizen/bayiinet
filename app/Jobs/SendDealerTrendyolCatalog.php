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

class SendDealerTrendyolCatalog implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 3600;

    public int $tries = 1;

    /**
     * @param  array<int, int>|null  $productIds  null = stoklu aktif tüm ürünler
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

    public function handle(DealerTrendyolService $trendyol): void
    {
        $dealer = Dealer::query()->find($this->dealerId);
        if (! $dealer || ! $dealer->hasTrendyolCredentials()) {
            Log::warning('SendDealerTrendyolCatalog: bayi veya credentials yok', ['dealer_id' => $this->dealerId]);

            return;
        }

        $cacheKey = 'trendyol_send_status_'.$dealer->id;
        Cache::put($cacheKey, [
            'status' => 'running',
            'started_at' => now()->toIso8601String(),
            'message' => 'Trendyol gönderimi devam ediyor…',
        ], now()->addHours(2));

        $started = microtime(true);

        try {
            $productIds = $this->productIds;
            if ($productIds === null) {
                $query = \App\Models\Product::query()
                    ->where('is_active', true)
                    ->where(function ($q) {
                        $q->where(function ($plain) {
                            $plain->where('has_variants', false)->where('stock', '>', 0);
                        })->orWhere(function ($v) {
                            $v->where('has_variants', true)
                                ->whereHas('variants', fn ($s) => $s->where('stock', '>', 0));
                        });
                    });

                if ($this->onlyMissing) {
                    $listed = $dealer->trendyolListings()
                        ->whereIn('status', ['sent', 'created'])
                        ->pluck('product_id')
                        ->unique()
                        ->all();
                    if ($listed !== []) {
                        $query->whereNotIn('id', $listed);
                    }
                }

                $productIds = $query->orderBy('id')->limit(5000)->pluck('id')->all();
            }

            if ($productIds === []) {
                Cache::put($cacheKey, [
                    'status' => 'done',
                    'sent' => 0,
                    'failed' => 0,
                    'message' => 'Gönderilecek yeni ürün yok.',
                    'finished_at' => now()->toIso8601String(),
                    'seconds' => 0,
                ], now()->addHours(6));

                return;
            }

            $result = $trendyol->send(
                $dealer,
                $productIds,
                $this->categoryId,
                $this->brandId,
                $this->attributes,
            );

            $seconds = round(microtime(true) - $started, 1);
            Cache::put($cacheKey, [
                'status' => 'done',
                'sent' => $result['sent'],
                'failed' => $result['failed'],
                'batches' => $result['batches'] ?? [],
                'errors' => array_slice($result['errors'] ?? [], 0, 10),
                'message' => "{$result['sent']} gönderildi, {$result['failed']} hatalı ({$seconds} sn)",
                'finished_at' => now()->toIso8601String(),
                'seconds' => $seconds,
            ], now()->addHours(6));

            Log::info('SendDealerTrendyolCatalog finished', [
                'dealer_id' => $dealer->id,
                'sent' => $result['sent'],
                'failed' => $result['failed'],
                'seconds' => $seconds,
            ]);
        } catch (Throwable $e) {
            Cache::put($cacheKey, [
                'status' => 'error',
                'message' => $e->getMessage(),
                'finished_at' => now()->toIso8601String(),
            ], now()->addHours(6));

            $dealer->update(['trendyol_last_error' => mb_substr($e->getMessage(), 0, 1000)]);
            Log::error('SendDealerTrendyolCatalog failed', [
                'dealer_id' => $this->dealerId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function failed(?Throwable $exception): void
    {
        Cache::put('trendyol_send_status_'.$this->dealerId, [
            'status' => 'error',
            'message' => $exception?->getMessage() ?? 'Bilinmeyen hata',
            'finished_at' => now()->toIso8601String(),
        ], now()->addHours(6));
    }
}
