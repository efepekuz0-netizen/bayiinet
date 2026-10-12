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
        // Pazaryeri komisyonu (%) — bayi satış fiyatı bu oran üzerinden hesaplanır
        'commission_rate' => (float) env('TRENDYOL_COMMISSION_RATE', 15),
        // Trendyol API çağrıları arasında beklenecek süre (milisaniye, hız sınırı güvenliği)
        'throttle_ms' => max(0, (int) env('TRENDYOL_THROTTLE_MS', 250)),
        // Tekil istekler ve paket (batch) istekleri için zaman aşımı (saniye)
        'http_timeout' => max(5, (int) env('TRENDYOL_HTTP_TIMEOUT', 20)),
        'batch_timeout' => max(20, (int) env('TRENDYOL_BATCH_TIMEOUT', 60)),
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

        // ------------------------------------------------------------------
        // Render gibi uykuya geçebilen / yeniden başlayan sunucularda
        // `hourly()` görevleri saat başında uyanılmadığı için atlanıyordu.
        // Artık görevler "son çalışmadan bu kadar dakika geçtiyse" kuralıyla
        // (catch-up) her dakika kontrol edilir. 0 yapılırsa klasik saatlik
        // davranışa döner.
        // ------------------------------------------------------------------
        'xml_interval_minutes' => (int) env('AUTOMATION_XML_INTERVAL', 55),
        'trendyol_interval_minutes' => (int) env('AUTOMATION_TRENDYOL_INTERVAL', 55),
        'recheck_interval_minutes' => (int) env('AUTOMATION_RECHECK_INTERVAL', 14),

        // Trendyol gönderiminden sonra fiyat/stok eşitlemesinin gecikmesi (dakika)
        'inventory_delay_minutes' => (int) env('AUTOMATION_INVENTORY_DELAY', 15),

        // Harici cron servisleri (cron-job.org, UptimeRobot, Render Cron) için
        // /cron/{görev}?token=... uçlarının anahtarı. Boşsa uçlar kapalıdır.
        'cron_token' => env('CRON_TOKEN'),
        // docker/start.sh APP_KEY'yi geçici ürettiyse 1 olur (tanı için).
        'app_key_ephemeral' => env('APP_KEY_EPHEMERAL', '0') === '1',
        // /cron/queue görevinin tek seferde kuyruğu boşaltmak için harcayacağı
        // en uzun süre (saniye).
        'cron_drain_seconds' => (int) env('CRON_DRAIN_SECONDS', 90),
    ],

    /*
     * XML içe aktarma.
     */
    'xml' => [
        // Bu boyutun üzerindeki dosyalar bellek dostu akış (XMLReader) ile
        // okunur; SimpleXML 100 MB'lık bir feed'i RAM'e sığdıramaz.
        'stream_threshold_bytes' => (int) env('XML_STREAM_THRESHOLD', 8 * 1024 * 1024),
        // İndirilen XML için üst sınır (byte)
        'max_bytes' => (int) env('XML_MAX_BYTES', 200 * 1024 * 1024),
        // Feed indirme zaman aşımı (saniye) — büyük dosyalar için cömert
        'download_timeout' => (int) env('XML_DOWNLOAD_TIMEOUT', 600),
    ],


    'hepsiburada' => [
        'base_url' => env('HEPSIBURADA_BASE_URL', 'https://mpop.hepsiburada.com'),
    ],
];
