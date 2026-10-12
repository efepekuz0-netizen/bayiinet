<?php

namespace App\Console\Commands;

use App\Jobs\SendDealerTrendyolRun;
use App\Jobs\SyncDealerTrendyolInventory;
use App\Models\Dealer;
use App\Services\AutomationStatus;
use App\Services\TrendyolSendProgress;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Saatlik: aktif + Trendyol bilgisi olan bayilere ürün gönder / fiyat-stok eşitle.
 */
class SyncTrendyolDealers extends Command
{
    protected $signature = 'bayiinet:sync-trendyol
                            {--dealer= : Sadece bu bayi id}
                            {--inventory-only : Sadece fiyat/stok eşitle, yeni ürün gönderme}
                            {--force : Devam eden gönderim olsa bile yeni gönderim başlat}';

    protected $description = 'Bayilerin Trendyol mağazasına saatlik ürün/fiyat/stok senkronu';

    public function handle(): int
    {
        // Zamanlayıcının ayakta olduğunu kaydet + bu turun denendiğini işaretle
        AutomationStatus::heartbeat('scheduler');
        AutomationStatus::markAttempt('trendyol_sync');

        if (! (bool) config('bayiinet.automation.trendyol_sync', true)) {
            $this->warn('Trendyol otomatik senkronu kapalı (AUTO_TRENDYOL_SYNC=false).');

            return self::SUCCESS;
        }

        $query = Dealer::query()
            ->where('status', 'active')
            ->where('auto_sync_enabled', true)
            ->whereNotNull('trendyol_seller_id')
            ->whereNotNull('trendyol_credentials');

        if ($id = $this->option('dealer')) {
            $query->where('id', (int) $id);
        }

        $dealers = $query->get();
        $this->info("Trendyol senkron: {$dealers->count()} bayi");

        $sent = 0;
        $skipped = 0;
        $failed = 0;
        $errors = [];

        foreach ($dealers as $dealer) {
            if (! $dealer->hasTrendyolCredentials()) {
                $skipped++;
                continue;
            }

            // Önceki gönderim hâlâ sürüyorsa aynı ürünleri tekrar tekrar göndermeyelim.
            // Ancak yarım kalmış (güncellenmeyen) bir durum varsa devam edilir:
            // aksi hâlde bayinin otomasyonu saatlerce durur.
            if (TrendyolSendProgress::isActive($dealer->id) && ! $this->option('force')) {
                if (! TrendyolSendProgress::isStale($dealer->id, 45)) {
                    $skipped++;
                    $this->line("  → {$dealer->company_name}: gönderim sürüyor, atlandı");

                    continue;
                }

                $this->warn("  → {$dealer->company_name}: önceki gönderim yarım kalmış, yeni tur başlatılıyor");
                TrendyolSendProgress::forget($dealer->id);
            }

            try {
                if ($this->option('inventory-only')) {
                    SyncDealerTrendyolInventory::dispatch($dealer->id, true);
                    $this->line("  → {$dealer->company_name}: fiyat/stok eşitleme kuyruğa alındı");
                } else {
                    // Yeni ürünler: masaüstü hızı (100'lük paket, tek iş).
                    // Eskiden 5'er ürünlük binlerce küçük iş kuyruğa atılıyordu;
                    // 15.000 ürünlük bir katalogda bu yüzlerce dakika sürüyordu.
                    SendDealerTrendyolRun::dispatch($dealer->id, null, null, null, [], true);

                    // Mevcut ilanların fiyat/stoğu gönderim bittikten sonra eşitlenir.
                    SyncDealerTrendyolInventory::dispatch($dealer->id, true)
                        ->delay(now()->addMinutes((int) config('bayiinet.automation.inventory_delay_minutes', 15)));

                    $this->line("  → {$dealer->company_name}: yeni ürünler ve ("
                        .(int) config('bayiinet.automation.inventory_delay_minutes', 15)
                        .' dk sonra) fiyat/stok kuyruğa alındı');
                }
                $sent++;
            } catch (Throwable $e) {
                $failed++;
                $errors[] = $dealer->company_name.': '.$e->getMessage();
                $dealer->update(['trendyol_last_error' => mb_substr($e->getMessage(), 0, 1000)]);
                Log::error('bayiinet:sync-trendyol failed', [
                    'dealer_id' => $dealer->id,
                    'error' => $e->getMessage(),
                ]);
                $this->error("  → {$dealer->company_name}: ".$e->getMessage());
            }
        }

        AutomationStatus::record(
            'trendyol_sync',
            $failed > 0 ? AutomationStatus::STATUS_WARNING : AutomationStatus::STATUS_OK,
            sprintf('%d bayi kuyruğa alındı, %d atlandı, %d hata.', $sent, $skipped, $failed)
                .($errors !== [] ? ' '.implode(' | ', array_slice($errors, 0, 3)) : '')
        );

        return self::SUCCESS;
    }
}
