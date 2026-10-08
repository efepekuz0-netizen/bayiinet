<?php

namespace App\Jobs;

use App\Models\Dealer;
use App\Models\Product;
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

    public int $timeout = 7200;

    public int $tries = 1;

    /**
     * @param  array<int, int>|null  $productIds  null = stoklu aktif tüm ürünler (limitsiz)
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
            'message' => 'Trendyol gönderimi başladı…',
            'sent' => 0,
            'failed' => 0,
        ], now()->addHours(4));

        $started = microtime(true);
        $totalSent = 0;
        $totalFailed = 0;
        $allBatches = [];
        $allErrors = [];

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

            $total = (clone $query)->count();
            if ($total === 0) {
                Cache::put($cacheKey, [
                    'status' => 'done',
                    'sent' => 0,
                    'failed' => 0,
                    'message' => 'Gönderilecek ürün yok (stoklu aktif ürün bulunamadı).',
                    'finished_at' => now()->toIso8601String(),
                    'seconds' => 0,
                ], now()->addHours(6));

                return;
            }

            Cache::put($cacheKey, [
                'status' => 'running',
                'message' => "0 / {$total} işleniyor…",
                'sent' => 0,
                'failed' => 0,
                'total' => $total,
                'started_at' => now()->toIso8601String(),
            ], now()->addHours(4));

            // Limitsiz: 300'lük parçalarda gönder (bellek + API)
            $processed = 0;
            $query->select('id')->chunkById(300, function ($rows) use (
                $trendyol, $dealer, $cacheKey, $total, &$totalSent, &$totalFailed, &$allBatches, &$allErrors, &$processed
            ) {
                $ids = $rows->pluck('id')->all();
                $result = $trendyol->send(
                    $dealer,
                    $ids,
                    $this->categoryId,
                    $this->brandId,
                    $this->attributes,
                );

                $totalSent += $result['sent'];
                $totalFailed += $result['failed'];
                $allBatches = array_merge($allBatches, $result['batches'] ?? []);
                $allErrors = array_merge($allErrors, $result['errors'] ?? []);
                $processed += count($ids);

                Cache::put($cacheKey, [
                    'status' => 'running',
                    'message' => "{$processed} / {$total} işlendi · gönderilen: {$totalSent} · hatalı: {$totalFailed}",
                    'sent' => $totalSent,
                    'failed' => $totalFailed,
                    'total' => $total,
                    'batches' => array_slice($allBatches, -5),
                    'errors' => array_slice(array_values(array_unique($allErrors)), 0, 15),
                    'started_at' => now()->toIso8601String(),
                ], now()->addHours(4));
            });

            $seconds = round(microtime(true) - $started, 1);
            Cache::put($cacheKey, [
                'status' => 'done',
                'sent' => $totalSent,
                'failed' => $totalFailed,
                'total' => $total,
                'batches' => array_values(array_unique($allBatches)),
                'errors' => array_slice(array_values(array_unique($allErrors)), 0, 20),
                'message' => "{$totalSent} gönderildi, {$totalFailed} hatalı / toplam {$total} ({$seconds} sn)",
                'finished_at' => now()->toIso8601String(),
                'seconds' => $seconds,
            ], now()->addHours(12));

            Log::info('SendDealerTrendyolCatalog finished', [
                'dealer_id' => $dealer->id,
                'sent' => $totalSent,
                'failed' => $totalFailed,
                'total' => $total,
                'seconds' => $seconds,
            ]);
        } catch (Throwable $e) {
            Cache::put($cacheKey, [
                'status' => 'error',
                'message' => $e->getMessage(),
                'sent' => $totalSent,
                'failed' => $totalFailed,
                'errors' => array_slice(array_values(array_unique($allErrors)), 0, 20),
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
