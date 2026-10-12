#!/bin/sh
# ---------------------------------------------------------------------------
# Bayiinet — ARKA PLAN İŞÇİSİ (Render Background Worker servisi)
#
# Render'da "Background Worker" tipinde servis açıp şunu verin:
#     dockerCommand: sh docker/worker.sh
#
# Böylece web servisi uyusa/dağıtım olsa bile XML içe aktarma ve Trendyol
# gönderimi kesintisiz sürer. (Ücretli plan gerekir.)
# ---------------------------------------------------------------------------
set -u

cd /app || exit 1

log() { echo "[bayiinet-worker] $*"; }

mkdir -p /usr/local/etc/php/conf.d 2>/dev/null || true
cat > /usr/local/etc/php/conf.d/99-bayiinet.ini 2>/dev/null << INIEOF || true
memory_limit = ${PHP_MEMORY_LIMIT:-512M}
max_execution_time = 0
INIEOF

if [ -z "${APP_KEY:-}" ]; then
    KEY_FILE="${APP_KEY_FILE:-/var/data/app_key}"
    if [ -f "$KEY_FILE" ]; then
        APP_KEY="$(cat "$KEY_FILE")"
        export APP_KEY
        log "APP_KEY kalıcı dosyadan okundu: $KEY_FILE"
    else
        log "HATA: APP_KEY tanımlı değil. İşçi servisi için Render ortam"
        log "değişkenlerine APP_KEY ekleyin (web servisiyle AYNI anahtar)."
        log "Aksi halde kuyruktaki Trendyol işleri şifreli kimlik bilgilerini"
        log "çözemez ve kalıcı olarak başarısız olur."
        exit 1
    fi
fi

mkdir -p storage/framework/cache/data storage/framework/views storage/logs storage/app/private/xml
chmod -R 775 storage bootstrap/cache 2>/dev/null || true

log "Kuyruk işçisi döngüsü başlıyor (default + marketplace)..."
while true; do
    php artisan queue:work database \
        --queue=default,marketplace \
        --max-time=1800 \
        --timeout=900 \
        --tries=1 \
        --memory=420 \
        --sleep=2 \
        --no-interaction || true
    sleep 3
done
