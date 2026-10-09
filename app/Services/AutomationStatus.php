<?php

namespace App\Services;

use App\Models\PlatformSetting;

/**
 * Zamanlanmış işlerin (saatlik XML yenileme, saatlik Trendyol senkronu,
 * batch doğrulama) son çalışma zamanı ve durumunu tutar.
 *
 * Böylece "otomasyon çalışıyor mu?" sorusu tahminle değil, panelden
 * görülebilen kayıtlarla yanıtlanır.
 */
class AutomationStatus
{
    /** @var array<string, array{label: string, schedule: string}> */
    public const JOBS = [
        'scheduler' => ['label' => 'Zamanlayıcı (schedule:work)', 'schedule' => 'sürekli'],
        'xml_import' => ['label' => 'XML kaynak yenileme', 'schedule' => 'saatte 1'],
        'trendyol_sync' => ['label' => 'Trendyol ürün gönderimi', 'schedule' => 'saatte 1'],
        'trendyol_inventory' => ['label' => 'Trendyol fiyat/stok eşitleme', 'schedule' => 'saatte 1'],
        'trendyol_recheck' => ['label' => 'Trendyol batch sonuç kontrolü', 'schedule' => '15 dakikada 1'],
    ];

    public const STATUS_OK = 'ok';

    public const STATUS_WARNING = 'warning';

    public const STATUS_ERROR = 'error';

    public const STATUS_IDLE = 'idle';

    public static function record(string $job, string $status = self::STATUS_OK, string $message = ''): void
    {
        self::heartbeat($job);

        PlatformSetting::write('automation:'.$job.':status', $status);
        PlatformSetting::write('automation:'.$job.':message', mb_substr($message, 0, 1500));
    }

    /** İşin çalıştığını (zamanlayıcının ayakta olduğunu) kaydeder. */
    public static function heartbeat(string $job = 'scheduler'): void
    {
        PlatformSetting::write('automation:'.$job.':ran_at', now()->toIso8601String());
    }

    /**
     * @return array{status: string, message: string, ran_at: ?string, label: string, schedule: string, late: bool}
     */
    public static function get(string $job): array
    {
        $definition = self::JOBS[$job] ?? ['label' => $job, 'schedule' => '-'];

        $ranAt = PlatformSetting::read('automation:'.$job.':ran_at', '');
        $ranAt = $ranAt !== '' ? $ranAt : null;

        $status = PlatformSetting::read('automation:'.$job.':status', '');
        $status = $status !== '' ? $status : self::STATUS_IDLE;

        $late = false;
        if ($ranAt !== null) {
            try {
                $late = now()->diffInMinutes(new \DateTimeImmutable($ranAt))
                    > (int) config('bayiinet.automation.heartbeat_max_delay', 75);
            } catch (\Throwable) {
                $late = false;
            }
        }

        return [
            'status' => $status,
            'message' => PlatformSetting::read('automation:'.$job.':message', ''),
            'ran_at' => $ranAt,
            'label' => $definition['label'],
            'schedule' => $definition['schedule'],
            'late' => $late,
        ];
    }

    /** @return array<string, array{status: string, message: string, ran_at: ?string, label: string, schedule: string, late: bool}> */
    public static function all(): array
    {
        $out = [];
        foreach (array_keys(self::JOBS) as $job) {
            $out[$job] = self::get($job);
        }

        return $out;
    }

    /** Zamanlayıcı son N dakika içinde çalıştı mı? */
    public static function schedulerAlive(): bool
    {
        $state = self::get('scheduler');

        return $state['ran_at'] !== null && ! $state['late'];
    }
}
