<?php

use App\Services\AutomationStatus;
use Illuminate\Support\Facades\Artisan;
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
| Neden `hourly()` yerine `everyMinute() + when(...)`?
|   Render (özellikle ücretsiz plan) servisi 15 dakika trafik almazsa uyutur,
|   dağıtımda yeniden başlatır. Saat başında uyanık olunamadığı için
|   `hourly()` görevleri sessizce atlanıyordu (örn. 12:00'de uyuduysa 13:00'te
|   tetiklenmez). Artık her dakika "son denemeden bu yana yeterli süre geçti mi?"
|   kontrol edilir; sunucu ne zaman uyanırsa uyansın görev en fazla birkaç
|   dakika gecikmeyle çalışır. `withoutOverlapping` çift çalışmayı engeller.
|
| Çalışması için `php artisan schedule:work` sürecinin ayakta olması gerekir
| (web konteynerinde docker/start.sh başlatır). Ayrıca Render Cron Job ile
| `php artisan schedule:run` ya da harici bir cron servisi ile
| `/cron/hourly?token=...` çağrılabilir — bkz. RENDER.md.
*/

// Nabız: panel "zamanlayıcı çalışmıyor" uyarısını bununla verir.
Schedule::call(function (): void {
    AutomationStatus::heartbeat('scheduler');
})->everyMinute()->name('automation-heartbeat')->withoutOverlapping(10);

// Saatlik XML kaynak yenileme + bayi feed önbellek temizliği (catch-up)
Schedule::command('bayiinet:sync-dealers')
    ->everyMinute()
    ->when(fn (): bool => AutomationStatus::dueFor(
        'xml_import',
        (int) config('bayiinet.automation.xml_interval_minutes', 55)
    ))
    ->withoutOverlapping(120)
    ->name('bayiinet-sync-dealers')
    ->onFailure(fn () => AutomationStatus::record('xml_import', AutomationStatus::STATUS_ERROR, 'Komut çalıştırılamadı.'));

// Saatlik Trendyol ürün gönderimi + fiyat/stok eşitleme (catch-up)
Schedule::command('bayiinet:sync-trendyol')
    ->everyMinute()
    ->when(fn (): bool => AutomationStatus::dueFor(
        'trendyol_sync',
        (int) config('bayiinet.automation.trendyol_interval_minutes', 55)
    ))
    ->withoutOverlapping(120)
    ->name('bayiinet-sync-trendyol')
    ->onFailure(fn () => AutomationStatus::record('trendyol_sync', AutomationStatus::STATUS_ERROR, 'Komut çalıştırılamadı.'));

// 15 dakikada bir: Trendyol'a gönderilmiş ama sonucu henüz okunamamış batch'ler
Schedule::command('bayiinet:recheck-trendyol')
    ->everyMinute()
    ->when(fn (): bool => AutomationStatus::dueFor(
        'trendyol_recheck',
        (int) config('bayiinet.automation.recheck_interval_minutes', 14)
    ))
    ->withoutOverlapping(15)
    ->name('bayiinet-recheck-trendyol');

// Kuyruk bakımı: eski başarısız iş kayıtlarını temizle (günde bir).
// Not: job_batches tablosu bu projede kullanılmıyor (migration yok),
// bu yüzden yalnızca failed_jobs temizlenir.
Schedule::call(function (): void {
    AutomationStatus::markAttempt('queue_prune');
    Artisan::call('queue:prune-failed', ['--hours' => 168]);
})->everyMinute()
    ->when(fn (): bool => AutomationStatus::dueFor('queue_prune', 60 * 24))
    ->withoutOverlapping(30)
    ->name('queue-prune-failed');
