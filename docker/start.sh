#!/bin/sh
cd /app || exit 1

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

# Job timeout 45s → worker timeout 60s (< retry_after 960)
# Döngü: worker ölürse / max-time dolunca yeniden başlar
(
  while true; do
    php artisan queue:work database \
      --queue=marketplace,default \
      --max-time=90 \
      --timeout=60 \
      --tries=1 \
      --memory=384 \
      --sleep=2 || true
    sleep 2
  done
) &

php artisan schedule:work &

export PHP_CLI_SERVER_WORKERS=1
exec php artisan serve --host=0.0.0.0 --port="${PORT:-10000}" --no-reload
