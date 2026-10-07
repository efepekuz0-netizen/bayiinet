<?php

namespace App\Console\Commands;

use App\Models\Dealer;
use App\Models\Source;
use App\Services\XmlImportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncDealerFeeds extends Command
{
    protected $signature = 'bayiinet:sync-dealers
                            {--skip-import : Sadece bayi feed önbelleğini temizle, XML kaynaklarını çekme}
                            {--source= : Sadece belirtilen source id yenilensin}';

    protected $description = 'Aktif XML kaynaklarını yeniler ve bayilere güncel feed aktarır (saatlik).';

    public function handle(XmlImportService $importService): int
    {
        $started = microtime(true);
        $imported = 0;
        $failed = 0;
        $dealersUpdated = 0;

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
                try {
                    $this->line("  → {$source->name} ({$source->url})");
                    $import = $importService->importFromUrl($source, null);

                    if ($import->status === 'completed') {
                        $imported++;
                        $this->info("    OK: +{$import->created_count} yeni, ~{$import->updated_count} güncellendi");
                        $source->update([
                            'last_imported_at' => now(),
                            'last_product_count' => $source->products()->count(),
                            'last_error' => null,
                        ]);
                    } else {
                        $failed++;
                        $this->error('    HATA: '.($import->log ?? 'bilinmeyen'));
                    }
                } catch (Throwable $e) {
                    $failed++;
                    Log::error('bayiinet:sync-dealers source failed', [
                        'source_id' => $source->id,
                        'message' => $e->getMessage(),
                    ]);
                    $source->update(['last_error' => $e->getMessage()]);
                    $this->error('    EXCEPTION: '.$e->getMessage());
                }
            }
        }

        // Katalog ve bayi feed önbelleklerini temizle → bayiler bir sonraki istekte güncel XML alır
        Cache::forget('xml_feed_catalog');

        $dealers = Dealer::query()
            ->where('status', 'active')
            ->where('auto_sync_enabled', true)
            ->get();

        foreach ($dealers as $dealer) {
            Cache::forget('xml_feed_dealer_'.$dealer->id);
            Cache::forget('xml_feed_'.$dealer->id);
            $dealer->update(['last_synced_at' => now()]);
            $dealersUpdated++;
        }

        // Auto_sync kapalı olanlar için de genel katalog temizlendi; token ile geldiklerinde güncel veri görürler
        $seconds = round(microtime(true) - $started, 2);

        Log::info('Bayiinet saatlik senkron tamamlandı', [
            'sources_ok' => $imported,
            'sources_failed' => $failed,
            'dealers' => $dealersUpdated,
            'seconds' => $seconds,
        ]);

        $this->info("Tamam: {$imported} kaynak OK, {$failed} hata, {$dealersUpdated} bayi feed güncellendi ({$seconds} sn)");

        return $failed > 0 && $imported === 0 ? self::FAILURE : self::SUCCESS;
    }
}
