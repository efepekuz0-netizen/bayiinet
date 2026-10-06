<?php

namespace App\Console\Commands;

use App\Models\Dealer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SyncDealerFeeds extends Command
{
    protected $signature = 'bayixml:sync-dealers';
    protected $description = 'Tüm aktif bayilerin XML feed önbelleğini temizler ve senkron zamanını günceller (saatlik).';

    public function handle(): int
    {
        $dealers = Dealer::query()
            ->where('status', 'active')
            ->where('auto_sync_enabled', true)
            ->get();

        $count = 0;
        foreach ($dealers as $dealer) {
            Cache::forget('xml_feed_'.$dealer->id);
            Cache::forget('xml_feed_catalog');
            $dealer->update(['last_synced_at' => now()]);
            $count++;
        }

        Log::info("BayiXML: {$count} bayi feed senkronu tamamlandı.");
        $this->info("{$count} bayi güncellendi.");

        return self::SUCCESS;
    }
}
