#!/bin/sh
cd /app || exit 1

# Büyük XML yükleme (URL indirme + dosya)
export PHP_INI_SCAN_DIR="${PHP_INI_SCAN_DIR:-}"
php -d upload_max_filesize=256M -d post_max_size=256M -d memory_limit=512M -r "echo '';" >/dev/null 2>&1 || true
# artisan/serve icin kalici ini
cat > /usr/local/etc/php/conf.d/99-uploads.ini << 'INIEOF'
upload_max_filesize = 256M
post_max_size = 256M
memory_limit = 512M
max_execution_time = 300
INIEOF

if [ -z "$APP_KEY" ]; then
    APP_KEY="$(php artisan key:generate --show)"
    export APP_KEY
fi

mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chmod -R 775 storage bootstrap/cache 2>/dev/null || true

php artisan config:clear || true
php artisan route:clear || true
php artisan view:clear || true
php artisan migrate --force || true
php artisan storage:link 2>/dev/null || true
php artisan bayiinet:ensure-admin || true
php artisan queue:flush 2>/dev/null || true
php artisan queue:prune-failed --hours=0 2>/dev/null || true

# Masaüstü tarzı job timeout 600s → worker timeout 620, retry_after 960
(
  while true; do
    php artisan queue:work database \
      --queue=marketplace,default \
      --max-time=300 \
      --timeout=620 \
      --tries=1 \
      --memory=512 \
      --sleep=2 || true
    sleep 2
  done
) &

php artisan schedule:work &

export PHP_CLI_SERVER_WORKERS=1
exec php artisan serve --host=0.0.0.0 --port="${PORT:-10000}" --no-reload
