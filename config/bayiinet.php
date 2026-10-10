<?php

/*
 * Bayiinet'e özgü yapılandırma.
 * Tüm ortam değişkenleri yalnızca buradan okunur; uygulama kodunda env() kullanılmaz
 * (config:cache ile çalışırken env() null döner).
 */

return [

    /*
     * İlk kurulumda oluşturulacak yönetici hesabı.
     * docker/start.sh -> php artisan bayiinet:ensure-admin
     */
    'admin' => [
        'email' => env('ADMIN_EMAIL'),
        'password' => env('ADMIN_PASSWORD'),
        'min_password_length' => 8,
    ],

    /*
     * Trendyol entegrasyonu varsayılanları.
     * Kâr/komisyon ayarları veritabanındaki pazaryeri bağlantısından gelir;
     * buradaki değerler yalnızca bağlantı tanımlı değilse kullanılır.
     */
    'trendyol' => [
        // Tek job'un Trendyol'a gönderdiği ürün sayısı
        'batch_size' => max(1, (int) env('TRENDYOL_BATCH_SIZE', 40)),
        // Trendyol'da markası bulunamayan ürünler için son çare marka numarası
        'fallback_brand_id' => (int) env('TRENDYOL_FALLBACK_BRAND_ID', 2613880),
        // Batch sonucunun senkron bekleneceği en fazla süre (saniye)
        'batch_wait_seconds' => (int) env('TRENDYOL_BATCH_WAIT', 9),
        // Onaylı ilan listesi taranırken izin verilen en fazla sayfa
        'max_inventory_pages' => (int) env('TRENDYOL_MAX_PAGES', 100),
        // API hatalarında yeniden deneme
        'retry_times' => (int) env('TRENDYOL_RETRY_TIMES', 3),
        'retry_sleep_ms' => (int) env('TRENDYOL_RETRY_SLEEP', 1500),
    ],

    /*
     * Zamanlanmış görevler (saatlik XML yenileme / Trendyol senkronu).
     */
    'automation' => [
        'xml_sync' => (bool) env('AUTO_XML_SYNC', true),
        'trendyol_sync' => (bool) env('AUTO_TRENDYOL_SYNC', true),
        // Zamanlayıcının ne kadar süre içinde çalışması gerektiği (uyarı eşiği, dakika)
        'heartbeat_max_delay' => (int) env('AUTOMATION_HEARTBEAT_DELAY', 75),
    ],


    'hepsiburada' => [
        'base_url' => env('HEPSIBURADA_BASE_URL', 'https://mpop.hepsiburada.com'),
    ],
];
