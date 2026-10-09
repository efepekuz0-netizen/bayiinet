#!/bin/sh
# Render konteyneri başlangıç betiği
cd /app || exit 1

# APP_KEY girilmemişse geçici bir anahtar üret (oturumlar her yeniden başlatmada sıfırlanır)
if [ -z "$APP_KEY" ]; then
    echo "UYARI: APP_KEY tanimli degil, gecici anahtar uretiliyor. Render > Environment bolumune APP_KEY ekleyin."
    APP_KEY="$(php artisan key:generate --show)"
    export APP_KEY
fi

php artisan config:clear
php artisan route:clear
php artisan view:clear

# Tabloları oluştur / güncelle. Başarısız olursa sebebi loglara yazılır, site yine de açılır.
php artisan migrate --force || echo "UYARI: migrate basarisiz. DB baglantisi ve env degerlerini kontrol edin."
php artisan storage:link 2>/dev/null || true
php artisan cache:clear 2>/dev/null || true
php artisan view:clear 2>/dev/null || true

# ADMIN_EMAIL ve ADMIN_PASSWORD tanımlıysa ilk yönetici hesabını oluştur
php artisan bayiinet:ensure-admin || true

# Takılı / başarısız kuyruk işlerini temizle (attempted too many times)
php artisan queue:flush 2>/dev/null || true
php artisan queue:prune-failed --hours=0 2>/dev/null || true
php artisan queue:clear marketplace --force 2>/dev/null || true

# Kuyruk işçileri: default (kar oranı, genel işler) + marketplace (Trendyol) + zamanlayıcı
php artisan queue:work --queue=default,marketplace --sleep=2 --tries=1 --timeout=960 --memory=512 &
php artisan schedule:work &

# --no-reload: ortam değişkenlerinin (APP_KEY, DB_*) uygulamaya iletilmesi için şart
export PHP_CLI_SERVER_WORKERS="${PHP_CLI_SERVER_WORKERS:-4}"
exec php artisan serve --host=0.0.0.0 --port="${PORT:-10000}" --no-reload
