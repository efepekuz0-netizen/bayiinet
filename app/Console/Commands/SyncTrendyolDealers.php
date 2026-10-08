<?php

namespace App\Console\Commands;

use App\Jobs\SendDealerTrendyolCatalog;
use App\Models\Dealer;
use App\Services\DealerTrendyolService;
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
                            {--inventory-only : Sadece fiyat/stok eşitle, yeni ürün gönderme}';

    protected $description = 'Bayilerin Trendyol mağazasına saatlik ürün/fiyat/stok senkronu';

    public function handle(DealerTrendyolService $trendyol): int
    {
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

        foreach ($dealers as $dealer) {
            if (! $dealer->hasTrendyolCredentials()) {
                continue;
            }

            try {
                if ($this->option('inventory-only')) {
                    $result = $trendyol->syncInventory($dealer);
                    $this->line("  → {$dealer->company_name}: {$result['updated']} fiyat/stok güncellendi");
                    Log::info('Trendyol inventory sync', [
                        'dealer_id' => $dealer->id,
                        'updated' => $result['updated'],
                    ]);
                } else {
                    // Yeni ürünleri arka planda gönder + mevcutların fiyat/stokunu eşitle
                    SendDealerTrendyolCatalog::dispatch(
                        $dealer->id,
                        null,
                        null,
                        null,
                        [],
                        true,
                    );
                    $inv = $trendyol->syncInventory($dealer);
                    $this->line("  → {$dealer->company_name}: yeni ürün kuyruğa alındı, {$inv['updated']} stok/fiyat eşitlendi");
                }
            } catch (Throwable $e) {
                $this->error("  → {$dealer->company_name}: ".$e->getMessage());
                $dealer->update(['trendyol_last_error' => mb_substr($e->getMessage(), 0, 1000)]);
                Log::error('bayiinet:sync-trendyol failed', [
                    'dealer_id' => $dealer->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return self::SUCCESS;
    }
}
