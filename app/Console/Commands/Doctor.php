<?php

namespace App\Console\Commands;

use App\Models\Dealer;
use App\Models\Source;
use App\Services\AutomationStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * "Otomasyon neden çalışmıyor?" sorusunu tek komutla yanıtlar.
 *
 *   php artisan bayiinet:doctor
 *   php artisan bayiinet:doctor --json
 *
 * Kontroller: APP_KEY kalıcılığı (Trendyol şifreleri çözülebiliyor mu),
 * veritabanı, kuyruk birikmesi, zamanlayıcı nabzı, depolama izinleri,
 * XML kaynaklarının son durumu ve cron anahtarı.
 */
class Doctor extends Command
{
    protected $signature = 'bayiinet:doctor {--json : Çıktıyı JSON olarak ver}';

    protected $description = 'Kurulum/otomasyon sağlık kontrolü (Render tanı aracı).';

    /** @var list<array{check: string, status: string, detail: string}> */
    private array $rows = [];

    public function handle(): int
    {
        $this->checkAppKey();
        $this->checkDatabase();
        $this->checkStorage();
        $this->checkQueue();
        $this->checkScheduler();
        $this->checkSources();
        $this->checkDealers();
        $this->checkCron();

        $hasError = collect($this->rows)->contains(fn (array $row): bool => $row['status'] === 'error');

        if ($this->option('json')) {
            $this->line(json_encode([
                'ok' => ! $hasError,
                'generated_at' => now()->toIso8601String(),
                'checks' => $this->rows,
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

            return $hasError ? self::FAILURE : self::SUCCESS;
        }

        $this->newLine();
        $this->line('<options=bold>Bayiinet — Otomasyon Sağlık Raporu</>');
        $this->newLine();

        foreach ($this->rows as $row) {
            $icon = match ($row['status']) {
                'ok' => '<fg=green>✔</>',
                'warn' => '<fg=yellow>!</>',
                default => '<fg=red>✘</>',
            };
            $this->line(sprintf('%s %-26s %s', $icon, $row['check'], $row['detail']));
        }

        $this->newLine();
        $this->line($hasError
            ? '<fg=red>Kritik sorun var: yukarıdaki ✘ satırlarına bakın.</>'
            : '<fg=green>Kritik sorun görünmüyor.</>');

        return $hasError ? self::FAILURE : self::SUCCESS;
    }

    private function add(string $check, string $status, string $detail): void
    {
        $this->rows[] = ['check' => $check, 'status' => $status, 'detail' => $detail];
    }

    private function checkAppKey(): void
    {
        $key = (string) config('app.key');

        if ($key === '') {
            $this->add('APP_KEY', 'error', 'Tanımlı değil! Trendyol şifreleri okunamaz.');

            return;
        }

        $ephemeral = (bool) config('bayiinet.automation.app_key_ephemeral', false);

        // Şifreli alanlar çözülebiliyor mu? (APP_KEY değişmişse null döner)
        $broken = 0;
        try {
            if (Schema::hasTable('dealers')) {
                Dealer::query()
                    ->whereNotNull('trendyol_credentials')
                    ->where('trendyol_credentials', '!=', '')
                    ->get(['id', 'trendyol_credentials'])
                    ->each(function (Dealer $dealer) use (&$broken): void {
                        if ($dealer->getRawOriginal('trendyol_credentials') !== null
                            && $dealer->getAttribute('trendyol_credentials') === null) {
                            $broken++;
                        }
                    });
            }
        } catch (Throwable $e) {
            $this->add('APP_KEY', 'warn', 'Şifre çözme testi yapılamadı: '.$e->getMessage());

            return;
        }

        if ($broken > 0) {
            $this->add('APP_KEY', 'error', $broken.' bayinin Trendyol API bilgisi çözülemiyor (anahtar değişmiş). Bilgileri yeniden girin.');

            return;
        }

        $this->add(
            'APP_KEY',
            $ephemeral ? 'warn' : 'ok',
            $ephemeral
                ? 'Geçici anahtar üretildi (kalıcı değil). Render ortam değişkenlerine APP_KEY ekleyin.'
                : 'Kalıcı ve şifreli alanlar okunabiliyor.'
        );
    }

    private function checkDatabase(): void
    {
        try {
            DB::select('select 1');
            $driver = DB::connection()->getDriverName();
            $migrations = Schema::hasTable('migrations') ? (int) DB::table('migrations')->count() : 0;
            $this->add('Veritabanı', 'ok', $driver.' bağlantısı çalışıyor ('.$migrations.' migration).');
        } catch (Throwable $e) {
            $this->add('Veritabanı', 'error', 'Bağlantı yok: '.mb_substr($e->getMessage(), 0, 160));
        }

        $this->add('Kuyruk sürücüsü', config('queue.default') === 'database' ? 'ok' : 'warn', (string) config('queue.default'));
        $this->add('Önbellek / oturum', 'ok', (string) config('cache.default').' / '.(string) config('session.driver'));
    }

    private function checkStorage(): void
    {
        $paths = [
            'storage/app/private' => storage_path('app/private'),
            'storage/framework/views' => storage_path('framework/views'),
            'storage/logs' => storage_path('logs'),
        ];

        foreach ($paths as $label => $path) {
            if (! is_dir($path)) {
                $this->add($label, 'error', 'Dizin yok.');

                continue;
            }
            if (! is_writable($path)) {
                $this->add($label, 'error', 'Yazılamıyor (izin sorunu).');

                continue;
            }
        }

        try {
            $probe = 'health/probe.txt';
            Storage::disk('local')->put($probe, (string) now()->timestamp);
            $ok = Storage::disk('local')->exists($probe);
            Storage::disk('local')->delete($probe);
            $this->add('Disk (local)', $ok ? 'ok' : 'error', $ok ? 'Yazma/okuma çalışıyor.' : 'Yazılan dosya okunamadı.');
        } catch (Throwable $e) {
            $this->add('Disk (local)', 'error', mb_substr($e->getMessage(), 0, 160));
        }
    }

    private function checkQueue(): void
    {
        try {
            $pending = (int) DB::table('jobs')->count();
            $reserved = (int) DB::table('jobs')->whereNotNull('reserved_at')->count();
            $failed = (int) DB::table('failed_jobs')->count();
            $oldest = DB::table('jobs')->min('available_at');

            $detail = $pending.' bekleyen · '.$reserved.' işleniyor · '.$failed.' başarısız';
            if ($oldest !== null) {
                $ageMinutes = (int) now()->diffInMinutes(now()->setTimestamp((int) $oldest));
                $detail .= ' · en eskisi '.$ageMinutes.' dk önce';
            }

            $status = 'ok';
            if ($oldest !== null && (int) now()->diffInMinutes(now()->setTimestamp((int) $oldest)) > 20) {
                $status = 'warn';
                $detail .= ' → kuyruk ilerlemiyor olabilir; worker çalışıyor mu? (docker/worker.sh veya RUN_QUEUE_WORKERS)';
            }
            if ($failed > 20) {
                $status = 'warn';
            }

            $this->add('Kuyruk', $status, $detail);
        } catch (Throwable $e) {
            $this->add('Kuyruk', 'warn', 'Okunamadı: '.mb_substr($e->getMessage(), 0, 140));
        }

        $retryAfter = (int) config('queue.connections.database.retry_after', 0);
        $this->add(
            'Kuyruk retry_after',
            $retryAfter >= 900 ? 'ok' : 'warn',
            $retryAfter.' sn (en uzun iş 840 sn olmalı; retry_after bundan büyük olmalı)'
        );
    }

    private function checkScheduler(): void
    {
        $state = AutomationStatus::get('scheduler');

        if ($state['ran_at'] === null) {
            $this->add('Zamanlayıcı', 'warn', 'Henüz nabız yok (schedule:work / cron kurulmamış olabilir).');

            return;
        }

        $minutes = (int) now()->diffInMinutes(new \DateTimeImmutable($state['ran_at']));

        $this->add(
            'Zamanlayıcı',
            $state['late'] ? 'warn' : 'ok',
            'Son nabız '.$minutes.' dk önce ('.$state['ran_at'].').'
        );
    }

    private function checkSources(): void
    {
        try {
            $sources = Source::query()->where('is_active', true)->get();
        } catch (Throwable $e) {
            $this->add('XML kaynakları', 'warn', 'Okunamadı: '.mb_substr($e->getMessage(), 0, 120));

            return;
        }

        if ($sources->isEmpty()) {
            $this->add('XML kaynakları', 'warn', 'Aktif kaynak yok.');

            return;
        }

        $withError = $sources->filter(fn (Source $s): bool => filled($s->last_error))->count();
        $never = $sources->filter(fn (Source $s): bool => $s->last_imported_at === null)->count();

        $this->add(
            'XML kaynakları',
            $withError > 0 ? 'warn' : 'ok',
            $sources->count().' aktif · '.$withError.' hatalı · '.$never.' hiç içe aktarılmamış.'
        );
    }

    private function checkDealers(): void
    {
        try {
            $total = (int) Dealer::query()->where('status', 'active')->count();
            $withApi = 0;
            Dealer::query()
                ->where('status', 'active')
                ->where('auto_sync_enabled', true)
                ->get()
                ->each(function (Dealer $dealer) use (&$withApi): void {
                    if ($dealer->hasTrendyolCredentials()) {
                        $withApi++;
                    }
                });

            $this->add('Aktif bayiler', 'ok', $total.' bayi · '.$withApi.' tanesi otomatik Trendyol senkronuna uygun.');
        } catch (Throwable $e) {
            $this->add('Aktif bayiler', 'warn', mb_substr($e->getMessage(), 0, 140));
        }
    }

    private function checkCron(): void
    {
        $token = trim((string) config('bayiinet.automation.cron_token'));

        $this->add(
            'Cron ucu (/cron/...)',
            $token === '' ? 'warn' : 'ok',
            $token === ''
                ? 'CRON_TOKEN tanımlı değil; uçlar kapalı. Harici cron kullanacaksanız ekleyin.'
                : 'Açık. Örnek: /cron/keepalive?token=*** ve /cron/hourly?token=***'
        );
    }
}
