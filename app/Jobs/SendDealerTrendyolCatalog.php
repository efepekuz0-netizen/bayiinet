<?php

namespace App\Jobs;

use App\Models\Dealer;
use App\Models\Product;
use App\Services\DealerTrendyolService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendDealerTrendyolCatalog implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** Worker timeout'undan kısa olmalı; uzun katalog chunk'larla ilerler */
    public int $timeout = 900;

    public int $tries = 1;

    public int $maxExceptions = 1;

    /** Aynı bayi için eşzamanlı ikinci gönderimi engelle (saniye) */
    public int $uniqueFor = 1800;

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

    public function uniqueId(): string
    {
        return 'trendyol-send-'.$this->dealerId;
    }

    public function handle(DealerTrendyolService $trendyol): void
    {
        $dealer = Dealer::query()->find($this->dealerId);
        if (! $dealer || ! $dealer->hasTrendyolCredentials()) {
            $this->writeStatus($this->dealerId, [
                'status' => 'error',
                'message' => 'Bayi bulunamadı veya Trendyol API bilgileri eksik.',
                'finished_at' => now()->toIso8601String(),
            ]);

            return;
        }

        $cacheKey = 'trendyol_send_status_'.$dealer->id;
        $this->writeStatus($dealer->id, [
            'status' => 'running',
            'started_at' => now()->toIso8601String(),
            'message' => 'Trendyol gönderimi başladı…',
            'sent' => 0,
            'failed' => 0,
        ]);

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
                $this->writeStatus($dealer->id, [
                    'status' => 'done',
                    'sent' => 0,
                    'failed' => 0,
                    'message' => 'Gönderilecek ürün yok (stoklu aktif ürün bulunamadı).',
                    'finished_at' => now()->toIso8601String(),
                    'seconds' => 0,
                ]);

                return;
            }

            $this->writeStatus($dealer->id, [
                'status' => 'running',
                'message' => "0 / {$total} işleniyor…",
                'sent' => 0,
                'failed' => 0,
                'total' => $total,
                'started_at' => now()->toIso8601String(),
            ]);

            $processed = 0;
            // Küçük parçalar: timeout / retry_after çakışmasını önler
            $query->select('id')->chunkById(100, function ($rows) use (
                $trendyol, $dealer, $total, &$totalSent, &$totalFailed, &$allBatches, &$allErrors, &$processed
            ) {
                $ids = $rows->pluck('id')->all();
                try {
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
                } catch (Throwable $chunkError) {
                    $totalFailed += count($ids);
                    $allErrors[] = $chunkError->getMessage();
                    Log::warning('Trendyol chunk failed', [
                        'dealer_id' => $dealer->id,
                        'error' => $chunkError->getMessage(),
                    ]);
                }

                $processed += count($ids);
                $this->writeStatus($dealer->id, [
                    'status' => 'running',
                    'message' => "{$processed} / {$total} işlendi · gönderilen: {$totalSent} · hatalı: {$totalFailed}",
                    'sent' => $totalSent,
                    'failed' => $totalFailed,
                    'total' => $total,
                    'batches' => array_slice($allBatches, -5),
                    'errors' => array_slice(array_values(array_unique($allErrors)), 0, 15),
                    'started_at' => now()->toIso8601String(),
                ]);
            });

            $seconds = round(microtime(true) - $started, 1);
            $this->writeStatus($dealer->id, [
                'status' => 'done',
                'sent' => $totalSent,
                'failed' => $totalFailed,
                'total' => $total,
                'batches' => array_values(array_unique($allBatches)),
                'errors' => array_slice(array_values(array_unique($allErrors)), 0, 20),
                'message' => "{$totalSent} gönderildi, {$totalFailed} hatalı / toplam {$total} ({$seconds} sn)",
                'finished_at' => now()->toIso8601String(),
                'seconds' => $seconds,
            ]);

            Log::info('SendDealerTrendyolCatalog finished', [
                'dealer_id' => $dealer->id,
                'sent' => $totalSent,
                'failed' => $totalFailed,
                'total' => $total,
                'seconds' => $seconds,
            ]);
        } catch (Throwable $e) {
            // Asla tekrar fırlatma → "attempted too many times" olmaz
            $this->writeStatus($dealer->id, [
                'status' => 'error',
                'message' => $e->getMessage(),
                'sent' => $totalSent,
                'failed' => $totalFailed,
                'errors' => array_slice(array_values(array_unique(array_merge($allErrors, [$e->getMessage()]))), 0, 20),
                'finished_at' => now()->toIso8601String(),
            ]);

            $dealer->update(['trendyol_last_error' => mb_substr($e->getMessage(), 0, 1000)]);
            Log::error('SendDealerTrendyolCatalog failed', [
                'dealer_id' => $this->dealerId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function failed(?Throwable $exception): void
    {
        $this->writeStatus($this->dealerId, [
            'status' => 'error',
            'message' => $exception?->getMessage() ?? 'İş kuyruğu başarısız oldu. Tekrar deneyin.',
            'finished_at' => now()->toIso8601String(),
        ]);
    }

    /** @param  array<string, mixed>  $data */
    private function writeStatus(int $dealerId, array $data): void
    {
        Cache::put('trendyol_send_status_'.$dealerId, $data, now()->addHours(12));
    }
}
