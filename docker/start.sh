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

# Takılı / failed işleri temizle (attempted too many times kalıntısı)
php artisan queue:flush 2>/dev/null || true
php artisan queue:prune-failed --hours=0 2>/dev/null || true

# Tek işçi, marketplace öncelikli, timeout > job.timeout, < retry_after
php artisan queue:work database --queue=marketplace,default --sleep=2 --tries=3 --timeout=120 --memory=512 --max-jobs=50 &
php artisan schedule:work &

export PHP_CLI_SERVER_WORKERS=1
exec php artisan serve --host=0.0.0.0 --port="${PORT:-10000}" --no-reload
