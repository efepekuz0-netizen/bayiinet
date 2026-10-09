<?php

namespace App\Console\Commands;

use App\Jobs\ImportSourceJob;
use App\Models\Dealer;
use App\Models\Source;
use App\Services\AutomationStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncDealerFeeds extends Command
{
    protected $signature = 'bayiinet:sync-dealers
                            {--skip-import : Sadece bayi feed önbelleğini temizle, XML kaynaklarını çekme}
                            {--source= : Sadece belirtilen source id yenilensin}
                            {--now : Kaynakları kuyruğa atmak yerine bu komut içinde senkron içe aktar}';

    protected $description = 'Aktif XML kaynaklarını yeniler ve bayilere güncel feed aktarır (saatlik).';

    public function handle(): int
    {
        // Zamanlayıcının ayakta olduğunu kaydet (panelde "otomasyon çalışıyor mu?" göstergesi)
        AutomationStatus::heartbeat('scheduler');

        if (! (bool) config('bayiinet.automation.xml_sync', true)) {
            $this->warn('XML otomatik senkronu kapalı (AUTO_XML_SYNC=false).');

            return self::SUCCESS;
        }

        $queued = 0;
        $failed = 0;

        if (! $this->option('skip-import')) {
            $query = Source::query()
                ->where('is_active', true)
                ->where('type', 'url')
                ->whereNotNull('url')
                ->orderBy('priority');

            if ($sourceId = $this->option('source')) {
                $query->where('id', (int) $sourceId);
            }

            $sources = $query->get();
            $this->info("XML kaynak yenileme: {$sources->count()} kaynak");

            foreach ($sources as $source) {
                $this->line("  → {$source->name} ({$source->url})");

                if ($this->option('now')) {
                    try {
                        $job = new ImportSourceJob($source->id, null, true);
                        $job->handle(app(\App\Services\XmlImportService::class));
                        $this->info('    tamamlandı');
                        $queued++;
                    } catch (Throwable $e) {
                        $failed++;
                        $this->error('    HATA: '.$e->getMessage());
                    }

                    continue;
                }

                try {
                    // Uzun süren içe aktarma arka planda yapılır; komut hemen biter.
                    ImportSourceJob::dispatch($source->id, null, true);
                    $queued++;
                    $this->info('    kuyruğa alındı');
                } catch (Throwable $e) {
                    $failed++;
                    Log::error('bayiinet:sync-dealers source failed', [
                        'source_id' => $source->id,
                        'message' => $e->getMessage(),
                    ]);
                    $this->error('    EXCEPTION: '.$e->getMessage());
                }
            }
        }

        // Katalog ve bayi feed önbelleklerini temizle → bayiler bir sonraki istekte güncel XML alır
        Cache::forget('xml_feed_catalog');
        Cache::forget('home_main_categories_v2');
        Cache::forget('admin_dash_stats_v2');

        $dealersUpdated = 0;
        Dealer::query()
            ->where('status', 'active')
            ->where('auto_sync_enabled', true)
            ->eachById(function (Dealer $dealer) use (&$dealersUpdated): void {
                Cache::forget('xml_feed_dealer_'.$dealer->id);
                $dealer->update(['last_synced_at' => now()]);
                $dealersUpdated++;
            }, 200);

        AutomationStatus::record(
            'xml_import',
            $failed > 0 ? AutomationStatus::STATUS_WARNING : AutomationStatus::STATUS_OK,
            sprintf('%d kaynak kuyruğa alındı, %d hata, %d bayi feed önbelleği temizlendi.', $queued, $failed, $dealersUpdated)
        );

        Log::info('Bayiinet saatlik XML senkronu tetiklendi', [
            'queued' => $queued,
            'failed' => $failed,
            'dealers' => $dealersUpdated,
        ]);

        $this->info("Tamam: {$queued} kaynak kuyruğa alındı, {$failed} hata, {$dealersUpdated} bayi feed güncellendi.");

        return $failed > 0 && $queued === 0 ? self::FAILURE : self::SUCCESS;
    }
}
