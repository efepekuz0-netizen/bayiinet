<?php

namespace App\Console\Commands;

use App\Models\Dealer;
use App\Services\AutomationStatus;
use App\Services\DealerTrendyolService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Trendyol'a gönderilmiş ama sonucu henüz okunamamış (status=sent) kalemlerin
 * batch sonuçlarını yeniden sorgular. Zamanlanmış görevden (routes/console.php)
 * ve /cron/recheck ucundan çağrılır.
 */
class RecheckTrendyolBatches extends Command
{
    protected $signature = 'bayiinet:recheck-trendyol
                            {--dealer= : Sadece bu bayi id}';

    protected $description = 'Bekleyen Trendyol batch sonuçlarını kontrol eder ve ilan durumlarını günceller.';

    public function handle(): int
    {
        AutomationStatus::markAttempt('trendyol_recheck');
        AutomationStatus::heartbeat('scheduler');

        $query = Dealer::query()
            ->where('status', 'active')
            ->whereNotNull('trendyol_seller_id');

        if ($id = $this->option('dealer')) {
            $query->where('id', (int) $id);
        }

        $service = app(DealerTrendyolService::class);
        $batches = 0;
        $checked = 0;
        $errors = [];

        foreach ($query->cursor() as $dealer) {
            if (! $dealer->hasTrendyolCredentials()) {
                continue;
            }

            try {
                $result = $service->recheckSentBatches($dealer);
                $batches += (int) ($result['batches'] ?? 0);
                $checked++;
            } catch (Throwable $e) {
                $errors[] = $dealer->company_name.': '.$e->getMessage();
                Log::warning('bayiinet:recheck-trendyol', [
                    'dealer_id' => $dealer->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $message = $batches.' batch kontrol edildi ('.$checked.' bayi)'
            .($errors !== [] ? ' · '.implode(' | ', array_slice($errors, 0, 3)) : '.');

        AutomationStatus::record(
            'trendyol_recheck',
            $errors !== [] ? AutomationStatus::STATUS_WARNING : AutomationStatus::STATUS_OK,
            $message
        );

        $this->info($message);

        return self::SUCCESS;
    }
}
