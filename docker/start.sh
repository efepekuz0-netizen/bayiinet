#!/bin/sh
cd /app || exit 1

if [ -z "$APP_KEY" ]; then
    echo "UYARI: APP_KEY tanimli degil"
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

# Takili / cok denenen isleri temizle
php artisan queue:flush 2>/dev/null || true
php artisan queue:prune-failed --hours=0 2>/dev/null || true

# tries=1: job sinifi kendi tries degerini kullanir; timeout < retry_after (960)
php artisan queue:work --queue=marketplace,default --sleep=2 --tries=1 --timeout=300 --memory=384 &
php artisan schedule:work &

export PHP_CLI_SERVER_WORKERS=1
exec php artisan serve --host=0.0.0.0 --port="${PORT:-10000}" --no-reload
