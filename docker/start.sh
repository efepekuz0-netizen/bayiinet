#!/bin/sh
cd /app || exit 1

if [ -z "$APP_KEY" ]; then
    echo "UYARI: APP_KEY tanimli degil, gecici anahtar uretiliyor. Render Environment'a sabit APP_KEY ekleyin."
    APP_KEY="$(php artisan key:generate --show)"
    export APP_KEY
fi

mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chmod -R 775 storage bootstrap/cache 2>/dev/null || true

php artisan config:clear || true
php artisan route:clear || true
php artisan view:clear || true

php artisan migrate --force || echo "UYARI: migrate basarisiz"
php artisan storage:link 2>/dev/null || true
php artisan bayiinet:ensure-admin || true

# OOM / 502 onlemi: tek kuyruk iscisi, dusuk worker
php artisan queue:work --queue=default,marketplace --sleep=3 --tries=2 --timeout=600 --memory=256 &
php artisan schedule:work &

# php built-in server: 1 worker (Render free/low RAM)
export PHP_CLI_SERVER_WORKERS=1
exec php artisan serve --host=0.0.0.0 --port="${PORT:-10000}" --no-reload
