#!/bin/sh
# ---------------------------------------------------------------------------
# Bayiinet — TEK SEFERLİK KOMUT (Render Cron Job servisi)
#
# Render'da "Cron Job" tipinde servis açıp:
#     schedule: "* * * * *"
#     dockerCommand: sh docker/cron.sh
# verirseniz her dakika `php artisan schedule:run` çalışır ve Laravel'in
# zamanlayıcısı tetiklenir (saatlik XML + Trendyol senkronu dahil).
#
# Argüman verilirse o komut çalıştırılır:
#     dockerCommand: sh docker/cron.sh "bayiinet:sync-trendyol"
# ---------------------------------------------------------------------------
set -u

cd /app || exit 1

log() { echo "[bayiinet-cron] $*"; }

mkdir -p /usr/local/etc/php/conf.d 2>/dev/null || true
cat > /usr/local/etc/php/conf.d/99-bayiinet.ini 2>/dev/null << INIEOF || true
memory_limit = ${PHP_MEMORY_LIMIT:-512M}
max_execution_time = 0
INIEOF

if [ -z "${APP_KEY:-}" ]; then
    KEY_FILE="${APP_KEY_FILE:-/var/data/app_key}"
    if [ -f "$KEY_FILE" ]; then
        APP_KEY="$(cat "$KEY_FILE")"
    else
        log "HATA: APP_KEY tanımlı değil (web servisiyle aynı anahtarı girin)."
        exit 1
    fi
    export APP_KEY
fi

mkdir -p storage/framework/cache/data storage/framework/views storage/logs
chmod -R 775 storage bootstrap/cache 2>/dev/null || true

php artisan config:clear >/dev/null 2>&1 || true

if [ "$#" -eq 0 ]; then
    log "schedule:run çalışıyor…"
    exec php artisan schedule:run --no-interaction
fi

log "Komut: $*"
exec php artisan "$@" --no-interaction
