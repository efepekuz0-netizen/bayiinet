<?php

namespace App\Http\Controllers;

use App\Services\AutomationStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Harici cron servisleri (cron-job.org, UptimeRobot, Render Cron Job …) için
 * token korumalı tetikleyici.
 *
 *   GET /cron/keepalive?token=...   → servisi uyanık tutar (panelde "sunucu ayakta")
 *   GET /cron/hourly?token=...      → saatlik XML + Trendyol senkronu (vadesi gelmişse)
 *   GET /cron/xml?token=...         → yalnız XML senkronu
 *   GET /cron/trendyol?token=...    → yalnız Trendyol gönderimi + fiyat/stok
 *   GET /cron/recheck?token=...     → bekleyen batch sonuçlarını kontrol eder
 *   GET /cron/queue?token=...       → kuyruğu bir süre boşaltır (worker yoksa kurtarıcı)
 *   GET /cron/doctor?token=...      → tanı raporu
 *
 * Ücretsiz Render planında Background Worker/Cron Job olmadığı için, ücretsiz
 * bir harici cron servisi bu uçları düzenli çağırarak otomasyonu ayakta tutar.
 *
 * Token: CRON_TOKEN ortam değişkeni. Boşsa uçlar tamamen kapalıdır (503).
 */
class CronController extends Controller
{
    public function run(Request $request, string $task): JsonResponse
    {
        $task = strtolower(trim($task));

        $expected = trim((string) config('bayiinet.automation.cron_token'));
        if ($expected === '') {
            return response()->json([
                'ok' => false,
                'message' => 'CRON_TOKEN tanımlı değil. Render > Environment bölümüne ekleyin.',
            ], 503);
        }

        $given = (string) ($request->query('token') ?: $request->header('X-Cron-Token', ''));
        if ($given === '' || ! hash_equals($expected, $given)) {
            return response()->json(['ok' => false, 'message' => 'Geçersiz token.'], 403);
        }

        $lock = Cache::lock('bayiinet_cron_'.$task, 900);

        if (! $lock->get()) {
            return response()->json([
                'ok' => true,
                'task' => $task,
                'message' => 'Aynı görev hâlâ çalışıyor; bu istek atlandı.',
            ], 202);
        }

        try {
            $result = match ($task) {
                'keepalive', 'ping' => $this->keepalive(),
                'hourly' => $this->hourly($request),
                'xml', 'xml-sync' => $this->runCommand('xml_import', 'bayiinet:sync-dealers', true),
                'trendyol', 'trendyol-sync' => $this->runCommand('trendyol_sync', 'bayiinet:sync-trendyol', true),
                'recheck' => $this->runCommand('trendyol_recheck', 'bayiinet:recheck-trendyol', true),
                'queue', 'drain' => $this->drain($request),
                'doctor' => $this->doctor(),
                default => response()->json([
                    'ok' => false,
                    'message' => 'Bilinmeyen görev: '.$task,
                    'tasks' => ['keepalive', 'hourly', 'xml', 'trendyol', 'recheck', 'queue', 'doctor'],
                ], 404),
            };
        } catch (Throwable $e) {
            report($e);

            $result = response()->json([
                'ok' => false,
                'task' => $task,
                'message' => $e->getMessage(),
            ], 500);
        } finally {
            $lock->release();
        }

        return $result;
    }

    private function keepalive(): JsonResponse
    {
        // Servis uyanık kaldığı sürece kuyruk işçileri ve zamanlayıcı da çalışır.
        AutomationStatus::record('host', AutomationStatus::STATUS_OK, 'Harici cron servisi servisi uyanık tutuyor.');

        return response()->json([
            'ok' => true,
            'task' => 'keepalive',
            'time' => now()->toIso8601String(),
            'scheduler_alive' => AutomationStatus::schedulerAlive(),
            'queue_pending' => $this->pendingJobs(),
        ]);
    }

    private function hourly(Request $request): JsonResponse
    {
        $force = $request->boolean('force');

        $runs = [
            'xml_import' => [
                'command' => 'bayiinet:sync-dealers',
                'due' => (int) config('bayiinet.automation.xml_interval_minutes', 55),
            ],
            'trendyol_sync' => [
                'command' => 'bayiinet:sync-trendyol',
                'due' => (int) config('bayiinet.automation.trendyol_interval_minutes', 55),
            ],
        ];

        $out = [];
        foreach ($runs as $job => $meta) {
            if (! $force && ! AutomationStatus::dueFor($job, $meta['due'])) {
                $out[$job] = 'vadesi gelmedi (atlandı)';

                continue;
            }

            $response = $this->runCommand($job, $meta['command'], true);
            $out[$job] = $response->getData(true)['output'] ?? 'çalıştırıldı';
        }

        return response()->json([
            'ok' => true,
            'task' => 'hourly',
            'time' => now()->toIso8601String(),
            'runs' => $out,
            'queue_pending' => $this->pendingJobs(),
        ]);
    }

    private function runCommand(string $job, string $command, bool $markAttempt): JsonResponse
    {
        if ($markAttempt) {
            AutomationStatus::markAttempt($job);
        }

        $status = Artisan::call($command);
        $output = trim(Artisan::output());

        return response()->json([
            'ok' => $status === 0,
            'task' => $command,
            'status' => $status,
            'output' => mb_substr($output, -1500),
            'queue_pending' => $this->pendingJobs(),
        ]);
    }

    /**
     * Kuyruğu sınırlı süre boşaltır. Worker çalışmıyorsa (ya da kilitli kaldıysa)
     * işlerin yine de ilerlemesini sağlar.
     */
    private function drain(Request $request): JsonResponse
    {
        $maxSeconds = (int) config('bayiinet.automation.cron_drain_seconds', 90);
        $seconds = (int) $request->query('seconds', $maxSeconds);
        $seconds = max(5, min(240, $seconds));

        $before = $this->pendingJobs();

        Artisan::call('queue:work', [
            'connection' => 'database',
            '--queue' => 'default,marketplace',
            '--stop-when-empty' => true,
            '--max-time' => $seconds,
            '--timeout' => 600,
            '--tries' => 1,
            '--memory' => 400,
        ]);

        return response()->json([
            'ok' => true,
            'task' => 'queue',
            'seconds' => $seconds,
            'pending_before' => $before,
            'pending_after' => $this->pendingJobs(),
            'output' => mb_substr(trim(Artisan::output()), -800),
        ]);
    }

    private function doctor(): JsonResponse
    {
        Artisan::call('bayiinet:doctor', ['--json' => true]);

        return response()->json([
            'ok' => true,
            'task' => 'doctor',
            'output' => mb_substr(trim(Artisan::output()), -4000),
        ]);
    }

    private function pendingJobs(): int
    {
        try {
            return (int) \Illuminate\Support\Facades\DB::table('jobs')->count();
        } catch (Throwable) {
            return -1;
        }
    }
}
