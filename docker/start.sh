#!/bin/sh
# ---------------------------------------------------------------------------
# Bayiinet — WEB servisi giriş betiği (Render Web Service / Docker)
#
# ROLE=web  → migrate + (varsayılan) kuyruk işçileri + zamanlayıcı + HTTP sunucu
# ROLE=worker → yalnız kuyruk işçisi (Render Background Worker için worker.sh)
# ROLE=cron   → tek seferlik komut çalıştırır (Render Cron Job için cron.sh)
#
# ÖNEMLİ (Render):
#  * APP_KEY mutlaka kalıcı olmalı. Render ortam değişkenlerine ekleyin:
#      APP_KEY=base64:...        (`php artisan key:generate --show`)
#    Kalıcı disk varsa anahtar /var/data/app_key dosyasına yazılır.
#    Anahtar değişirse Trendyol API şifreleri çözülemez → gönderim durur.
#  * Kuyruk SİLİNMEZ. (Eski sürüm her açılışta `queue:flush` yapıyordu.)
# ---------------------------------------------------------------------------
set -u

cd /app || exit 1

log() { echo "[bayiinet] $*"; }

# --- PHP ayarları: büyük XML, uzun içe aktarma ve bellek -------------------
mkdir -p /usr/local/etc/php/conf.d 2>/dev/null || true
cat > /usr/local/etc/php/conf.d/99-bayiinet.ini 2>/dev/null << INIEOF || true
upload_max_filesize = 256M
post_max_size = 256M
memory_limit = ${PHP_MEMORY_LIMIT:-512M}
max_execution_time = 300
max_input_time = 300
default_socket_timeout = 120
INIEOF

# --- APP_KEY: kalıcı olmalı ------------------------------------------------
KEY_FILE="${APP_KEY_FILE:-/var/data/app_key}"
if [ -z "${APP_KEY:-}" ]; then
    if [ -f "$KEY_FILE" ]; then
        APP_KEY="$(cat "$KEY_FILE")"
        export APP_KEY
        log "APP_KEY kalıcı dosyadan okundu: $KEY_FILE"
    else
        APP_KEY="$(php artisan key:generate --show 2>/dev/null)"
        if [ -n "$APP_KEY" ]; then
            export APP_KEY
            if mkdir -p "$(dirname "$KEY_FILE")" 2>/dev/null && printf '%s' "$APP_KEY" > "$KEY_FILE" 2>/dev/null; then
                APP_KEY_EPHEMERAL=0
                log "APP_KEY üretildi ve $KEY_FILE içine yazıldı (kalıcı disk)."
            else
                APP_KEY_EPHEMERAL=1
                log "!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!"
                log "!! UYARI: APP_KEY verilmedi ve kalıcı disk yok."
                log "!! Her yeniden başlatmada anahtar DEĞİŞECEK; bayilerin"
                log "!! Trendyol API şifreleri çözülemez hale gelir."
                log "!! Render > Environment bölümüne APP_KEY ekleyin:"
                log "!!   php artisan key:generate --show   (yerelde çalıştırın)"
                log "!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!"
            fi
        fi
    fi
fi
export APP_KEY_EPHEMERAL="${APP_KEY_EPHEMERAL:-0}"

if [ -z "${APP_KEY:-}" ] && [ "${APP_KEY_STRICT:-0}" = "1" ]; then
    log "HATA: APP_KEY boş. Worker/Cron servisi gönderim yapamaz; servis durduruldu."
    exit 1
fi

# --- Kalıcı dizinler -------------------------------------------------------
mkdir -p storage/framework/cache/data \
         storage/framework/sessions \
         storage/framework/views \
         storage/logs \
         storage/app/private/xml \
         storage/app/public \
         bootstrap/cache
chmod -R 775 storage bootstrap/cache 2>/dev/null || true

# --- Laravel ön hazırlık ---------------------------------------------------
php artisan config:clear >/dev/null 2>&1 || true
php artisan route:clear  >/dev/null 2>&1 || true
php artisan view:clear   >/dev/null 2>&1 || true

if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    php artisan migrate --force || log "UYARI: migrate başarısız oldu."
    php artisan bayiinet:ensure-admin || true
    php artisan storage:link 2>/dev/null || true
else
    log "migrate atlandı (RUN_MIGRATIONS != true)."
fi

# Başarısız iş kayıtları birikir; haftalık temizlik yeterli.
php artisan queue:prune-failed --hours=168 2>/dev/null || true

# --- Kuyruk işçileri -------------------------------------------------------
# XML içe aktarma 'default', Trendyol işleri 'marketplace' kuyruğunda.
# İkisi ayrı işçide çalışırsa uzun süren XML aktarımı Trendyol gönderimini bloklamaz.
start_worker() {
    name="$1"
    queue="$2"
    (
        while true; do
            php artisan queue:work database \
                --queue="$queue" \
                --max-time=300 \
                --timeout=900 \
                --tries=1 \
                --memory=384 \
                --sleep=2 \
                --no-interaction || true
            sleep 2
        done
    ) &
    log "Kuyruk işçisi başlatıldı: $name ($queue)"
}

if [ "${RUN_QUEUE_WORKERS:-true}" = "true" ]; then
    start_worker "xml" "default"
    start_worker "marketplace" "marketplace"
else
    log "Kuyruk işçileri kapalı (RUN_QUEUE_WORKERS=false)."
fi

# --- Zamanlayıcı -----------------------------------------------------------
if [ "${RUN_SCHEDULER:-true}" = "true" ]; then
    (
        while true; do
            php artisan schedule:work --no-interaction || true
            sleep 5
        done
    ) &
    log "Zamanlayıcı başlatıldı (schedule:work)."
else
    log "Zamanlayıcı kapalı (RUN_SCHEDULER=false) — Render Cron Job kullanın."
fi

# --- HTTP sunucu -----------------------------------------------------------
export PHP_CLI_SERVER_WORKERS="${PHP_CLI_SERVER_WORKERS:-3}"
log "HTTP sunucu 0.0.0.0:${PORT:-10000} (PHP_CLI_SERVER_WORKERS=$PHP_CLI_SERVER_WORKERS)"
exec php artisan serve --host=0.0.0.0 --port="${PORT:-10000}" --no-reload
