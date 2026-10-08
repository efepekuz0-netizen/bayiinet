<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use Illuminate\Support\Facades\Schedule;

Schedule::command('bayiinet:sync-dealers')->hourly();
Schedule::command('bayiinet:sync-trendyol')->hourly();


Schedule::call(function () {
    $dealers = \App\Models\Dealer::query()
        ->where('status', 'active')
        ->whereNotNull('trendyol_seller_id')
        ->get();
    $svc = app(\App\Services\DealerTrendyolService::class);
    foreach ($dealers as $dealer) {
        if (! $dealer->hasTrendyolCredentials()) {
            continue;
        }
        try {
            $svc->recheckSentBatches($dealer);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('scheduled trendyol recheck', [
                'dealer_id' => $dealer->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
})->everyFifteenMinutes()->name('trendyol-recheck-sent')->withoutOverlapping();
