<?php

use App\Models\Dealer;
use App\Services\AutomationStatus;
use App\Services\DealerTrendyolService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(\Illuminate\Foundation\Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Otomasyon
|--------------------------------------------------------------------------
| Tüm zamanlanmış işler durumunu AutomationStatus'a yazar; yönetim panelindeki
| "Otomasyon" ekranından son çalışma zamanı ve sonuçları görülebilir.
|
| Not: Bu görevlerin çalışabilmesi için `php artisan schedule:work` sürecinin
| ayakta olması gerekir (docker/start.sh içinde başlatılır; Render'da ayrı bir
| "Background Worker" servisi olarak çalıştırmak daha güvenlidir).
*/

// Zamanlayıcı nabzı: panel "zamanlayıcı çalışmıyor" uyarısını bununla verir.
Schedule::call(function (): void {
    AutomationStatus::heartbeat('scheduler');
})->everyFiveMinutes()->name('automation-heartbeat')->withoutOverlapping(10);

// Saatlik XML kaynak yenileme + bayi feed önbellek temizliği
Schedule::command('bayiinet:sync-dealers')
    ->hourly()
    ->withoutOverlapping(120)
    ->name('bayiinet-sync-dealers')
    ->onFailure(fn () => AutomationStatus::record('xml_import', AutomationStatus::STATUS_ERROR, 'Komut çalıştırılamadı.'));

// Saatlik Trendyol ürün gönderimi + fiyat/stok eşitleme
Schedule::command('bayiinet:sync-trendyol')
    ->hourly()
    ->withoutOverlapping(120)
    ->name('bayiinet-sync-trendyol')
    ->onFailure(fn () => AutomationStatus::record('trendyol_sync', AutomationStatus::STATUS_ERROR, 'Komut çalıştırılamadı.'));

// 15 dakikada bir: Trendyol'a gönderilmiş ama sonucu henüz okunamamış batch'ler
Schedule::call(function (): void {
    AutomationStatus::heartbeat('scheduler');

    $dealers = Dealer::query()
        ->where('status', 'active')
        ->whereNotNull('trendyol_seller_id')
        ->get();

    $service = app(DealerTrendyolService::class);
    $batches = 0;
    $errors = [];

    foreach ($dealers as $dealer) {
        if (! $dealer->hasTrendyolCredentials()) {
            continue;
        }

        try {
            $result = $service->recheckSentBatches($dealer);
            $batches += (int) ($result['batches'] ?? 0);
        } catch (\Throwable $e) {
            $errors[] = $dealer->company_name.': '.$e->getMessage();
            Log::warning('scheduled trendyol recheck', [
                'dealer_id' => $dealer->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    AutomationStatus::record(
        'trendyol_recheck',
        $errors !== [] ? AutomationStatus::STATUS_WARNING : AutomationStatus::STATUS_OK,
        $batches.' batch kontrol edildi'.($errors !== [] ? ' · '.implode(' | ', array_slice($errors, 0, 3)) : '.')
    );
})->everyFifteenMinutes()->name('trendyol-recheck-sent')->withoutOverlapping(10);

// Kuyruk bakımı: eski başarısız iş kayıtlarını temizle
// Not: job_batches tablosu bu projede kullanılmıyor (migration yok),
// bu yüzden yalnızca failed_jobs temizlenir.
Schedule::command('queue:prune-failed --hours=168')->weekly()->name('queue-prune-failed');
