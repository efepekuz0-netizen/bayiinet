<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Kritik yönetici eylemlerinin denetim izi (audit log).
 *
 * Bakiye hareketleri dışında hiçbir admin işlemi kayda geçmiyordu: fiyat
 * değişikliği, bayi onay/askı ve Trendyol'dan toplu ürün silme iz bırakmadan
 * yapılıyordu. Bu sınıf tek merkezden `admin.audit` kanalına yazar.
 */
class AdminAudit
{
    public static function log(string $action, string $summary, array $context = []): void
    {
        Log::info('admin.audit', [
            'action' => $action,
            'summary' => $summary,
            'user_id' => auth()->id(),
            'user_email' => auth()->user()?->email,
            'ip' => request()?->ip(),
            'url' => request()?->fullUrl(),
        ] + $context);
    }
}
