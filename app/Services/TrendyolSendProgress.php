<?php

namespace App\Services;

use App\Models\DealerTrendyolListing;
use Illuminate\Support\Facades\Cache;

/**
 * Bir bayinin Trendyol gönderim durumunun tek merkezden yönetimi.
 *
 * Geçmişte bu durum (cache anahtarı ve sayaçlar) controller, orchestrator job,
 * batch job ve doğrulama job'ı arasında dağınık ve *artırmalı* olarak
 * güncelleniyordu: Trendyol ürünü kabul ettiği hâlde sayaçlar 0'da kalıyor,
 * "ürün gönderilemiyor" sanılıyordu. Artık tüm sayaçlar, batch bazlı ve
 * tekrar edilebilir (idempotent) biçimde tutuluyor.
 */
class TrendyolSendProgress
{
    /** Durum önbelleğinin ömrü (saat) */
    public const TTL_HOURS = 12;

    public const STATUS_IDLE = 'idle';

    public const STATUS_QUEUED = 'queued';

    public const STATUS_RUNNING = 'running';

    public const STATUS_DONE = 'done';

    public const STATUS_ERROR = 'error';

    public const STATUS_CANCELLED = 'cancelled';

    public static function key(int $dealerId): string
    {
        return 'trendyol_send_status_'.$dealerId;
    }

    /**
     * Bir batch, Trendyol tarafından kabul edildiğinde kaydedilir.
     * Sayaçlar bu kayıtlardan türetilir:
     *   gönderilen  = oluşan (created) + fiyat/stoğu güncellenen mevcut ilanlar
     *   bekleyen    = Trendyol'a iletildi ama sonucu henüz okunamayan kalemler
     *   hatalı      = reddedilen + hazırlanamayan kalemler
     */
    public static function start(int $dealerId, int $total, int $totalBatches, string $message = ''): void
    {
        self::write($dealerId, function (array $status) use ($total, $totalBatches, $message): array {
            return array_merge($status, [
                'status' => self::STATUS_RUNNING,
                'total' => $total,
                'total_batches' => $totalBatches,
                'processed' => 0,
                'batches_done' => 0,
                'batches' => [],
                'direct_sent' => 0,
                'direct_failed' => 0,
                'errors' => [],
                'batch_ids' => [],
                'started_at' => now()->toIso8601String(),
                'updated_at' => now()->toIso8601String(),
                'finished_at' => null,
                'message' => $message !== '' ? $message : "0 / {$total} gönderiliyor ({$totalBatches} parça)…",
            ]);
        });
    }

    public static function queued(int $dealerId, string $message): void
    {
        self::write($dealerId, function (array $status) use ($message): array {
            return array_merge($status, [
                'status' => self::STATUS_QUEUED,
                'message' => $message,
                'updated_at' => now()->toIso8601String(),
                'finished_at' => null,
            ]);
        });
    }

    /**
     * Bir batch job'ı tamamlandığında çağrılır.
     *
     * @param  int  $processed  işlenen katalog kalemi
     * @param  int  $directSent  doğrudan başarılı (fiyat/stoğu güncellenen) kalem
     * @param  int  $directFailed  Trendyol'a hiç iletilemeyen kalem
     * @param  list<string>  $batchIds  oluşturulan Trendyol batch numaraları
     * @param  list<string>  $errors
     */
    public static function addBatch(
        int $dealerId,
        int $processed,
        int $directSent,
        int $directFailed,
        array $batchIds = [],
        array $errors = [],
    ): void {
        self::write($dealerId, function (array $status) use ($processed, $directSent, $directFailed, $batchIds, $errors): array {
            $status['status'] = self::STATUS_RUNNING;
            $status['processed'] = (int) ($status['processed'] ?? 0) + $processed;
            $status['direct_sent'] = (int) ($status['direct_sent'] ?? 0) + $directSent;
            $status['direct_failed'] = (int) ($status['direct_failed'] ?? 0) + $directFailed;
            $status['batches_done'] = (int) ($status['batches_done'] ?? 0) + 1;

            $status['errors'] = array_slice(array_values(array_unique(array_merge(
                (array) ($status['errors'] ?? []),
                $errors,
            ))), 0, 20);

            $status['batch_ids'] = array_slice(array_values(array_unique(array_merge(
                (array) ($status['batch_ids'] ?? []),
                $batchIds,
            ))), -30);

            return $status;
        });
    }

    /** Trendyol'un bir create isteğini kabul ettiği batch'i kaydeder. */
    public static function registerBatch(int $dealerId, string $batchId, int $size): void
    {
        self::write($dealerId, function (array $status) use ($batchId, $size): array {
            $batches = (array) ($status['batches'] ?? []);
            $batches[$batchId] = [
                'size' => max(0, $size),
                'created' => (int) ($batches[$batchId]['created'] ?? 0),
                'failed' => (int) ($batches[$batchId]['failed'] ?? 0),
            ];

            $status['batches'] = $batches;

            return $status;
        });
    }

    /**
     * Bir batch'in sonucunu veritabanındaki listing durumlarından yeniden hesaplar.
     * Tekrar tekrar çağrılabilir — aynı sonucu verir (idempotent).
     *
     * @return array{created: int, failed: int, pending: int}
     */
    public static function resolveBatch(int $dealerId, string $batchId): array
    {
        $counts = DealerTrendyolListing::query()
            ->where('dealer_id', $dealerId)
            ->where('batch_request_id', $batchId)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        $created = (int) ($counts['created'] ?? 0);
        $failed = (int) ($counts['failed'] ?? 0);
        $pending = (int) ($counts['sent'] ?? 0);

        self::write($dealerId, function (array $status) use ($batchId, $created, $failed): array {
            $batches = (array) ($status['batches'] ?? []);
            $batches[$batchId] = [
                'size' => (int) ($batches[$batchId]['size'] ?? max($created + $failed, 1)),
                'created' => $created,
                'failed' => $failed,
            ];
            $status['batches'] = $batches;

            return $status;
        });

        return ['created' => $created, 'failed' => $failed, 'pending' => $pending];
    }

    /** Bayinin bekleyen tüm batch'lerini yeniden hesaplar. */
    public static function resolveAll(int $dealerId): void
    {
        $batchIds = DealerTrendyolListing::query()
            ->where('dealer_id', $dealerId)
            ->whereNotNull('batch_request_id')
            ->distinct()
            ->pluck('batch_request_id')
            ->filter()
            ->values()
            ->all();

        foreach ($batchIds as $batchId) {
            self::resolveBatch($dealerId, (string) $batchId);
        }
    }

    public static function finish(int $dealerId, ?string $message = null): void
    {
        self::write($dealerId, function (array $status) use ($message): array {
            $status['status'] = self::STATUS_DONE;
            $status['finished_at'] = now()->toIso8601String();
            $status['updated_at'] = now()->toIso8601String();

            return $status;
        }, $message);
    }

    public static function fail(int $dealerId, string $message): void
    {
        self::write($dealerId, function (array $status): array {
            $status['status'] = self::STATUS_ERROR;
            $status['finished_at'] = now()->toIso8601String();
            $status['updated_at'] = now()->toIso8601String();

            return $status;
        }, $message);
    }

    public static function cancel(int $dealerId, string $message = 'Gönderim durduruldu. Kuyruktaki işler atlanacak.'): void
    {
        self::write($dealerId, function (array $status): array {
            $status['status'] = self::STATUS_CANCELLED;
            $status['finished_at'] = now()->toIso8601String();
            $status['updated_at'] = now()->toIso8601String();

            return $status;
        }, $message);
    }

    public static function forget(int $dealerId): void
    {
        Cache::forget(self::key($dealerId));
    }

    /** @return array<string, mixed> */
    public static function get(int $dealerId): array
    {
        $status = Cache::get(self::key($dealerId));

        return is_array($status) ? $status : [];
    }

    public static function status(int $dealerId): string
    {
        return (string) (self::get($dealerId)['status'] ?? self::STATUS_IDLE);
    }

    /** Gönderim sürüyor mu? (yeni gönderim başlatılmamalı) */
    public static function isActive(int $dealerId): bool
    {
        return in_array(self::status($dealerId), [self::STATUS_QUEUED, self::STATUS_RUNNING], true);
    }

    public static function isCancelled(int $dealerId): bool
    {
        return self::status($dealerId) === self::STATUS_CANCELLED;
    }

    /**
     * @param  callable(array<string,mixed>): array<string,mixed>  $callback
     */
    protected static function write(int $dealerId, callable $callback, ?string $message = null): void
    {
        // Birden fazla işçi aynı bayi için aynı anda güncelleme yapabilir;
        // kilitsiz okuma-yazma sayaçların kaybolmasına yol açıyordu.
        try {
            Cache::lock('trendyol_send_lock_'.$dealerId, 10)->block(5, function () use ($dealerId, $callback, $message): void {
                self::persist($dealerId, $callback(self::get($dealerId)), $message);
            });
        } catch (\Throwable) {
            // Kilit alınamazsa kilitsiz yaz: sayaç kaybı, hiç yazmamaktan iyidir.
            self::persist($dealerId, $callback(self::get($dealerId)), $message);
        }
    }

    /** @param  array<string,mixed>  $status */
    protected static function persist(int $dealerId, array $status, ?string $message = null): void
    {
        $status = self::withDerived($status);

        if ($message !== null) {
            $status['message'] = $message;
        } else {
            $status['message'] = self::message($status);
        }

        $status['updated_at'] = now()->toIso8601String();

        Cache::put(self::key($dealerId), $status, now()->addHours(self::TTL_HOURS));
    }

    /** @param  array<string,mixed>  $status */
    protected static function withDerived(array $status): array
    {
        $created = 0;
        $failed = 0;
        $pending = 0;

        foreach ((array) ($status['batches'] ?? []) as $batch) {
            $size = (int) ($batch['size'] ?? 0);
            $batchCreated = (int) ($batch['created'] ?? 0);
            $batchFailed = (int) ($batch['failed'] ?? 0);

            $created += $batchCreated;
            $failed += $batchFailed;
            $pending += max(0, $size - $batchCreated - $batchFailed);
        }

        $status['sent'] = $created + (int) ($status['direct_sent'] ?? 0);
        $status['pending'] = $pending;
        $status['failed'] = $failed + (int) ($status['direct_failed'] ?? 0);

        // Tüm parçalar işlendiyse gönderim bitmiş sayılır (batch sonuçları
        // sonra gelse bile sayaçlar otomatik güncellenir).
        $totalBatches = (int) ($status['total_batches'] ?? 0);
        $batchesDone = (int) ($status['batches_done'] ?? 0);
        if (($status['status'] ?? '') === self::STATUS_RUNNING && $totalBatches > 0 && $batchesDone >= $totalBatches) {
            $status['status'] = self::STATUS_DONE;
            if (empty($status['finished_at'])) {
                $status['finished_at'] = now()->toIso8601String();
            }
        }

        return $status;
    }

    /** @param  array<string,mixed>  $status */
    protected static function message(array $status): string
    {
        $processed = (int) ($status['processed'] ?? 0);
        $total = (int) ($status['total'] ?? 0);

        if (($status['status'] ?? '') === self::STATUS_DONE) {
            $text = sprintf(
                'Bitti: %d gönderildi, %d doğrulama bekliyor, %d hatalı / %d',
                (int) ($status['sent'] ?? 0),
                (int) ($status['pending'] ?? 0),
                (int) ($status['failed'] ?? 0),
                $total,
            );

            if (($status['pending'] ?? 0) > 0) {
                $text .= ' — bekleyenler Trendyol sonuç verdikçe otomatik güncellenir.';
            }

            return $text;
        }

        return sprintf(
            '%d / %d işlendi · gönderilen: %d · doğrulama bekleyen: %d · hatalı: %d',
            $processed,
            $total,
            (int) ($status['sent'] ?? 0),
            (int) ($status['pending'] ?? 0),
            (int) ($status['failed'] ?? 0),
        );
    }
}
