<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * APP_KEY değişince eski şifreli veri DecryptException fırlatmasın diye güvenli cast.
 */
class SafeEncryptedArray implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            $decoded = Crypt::decryptString($value);
            $data = json_decode($decoded, true);

            return is_array($data) ? $data : null;
        } catch (Throwable $e) {
            Log::warning('encrypted field decrypt failed', [
                'model' => $model::class,
                'key' => $key,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : ['raw' => $value];
        }

        if (! is_array($value)) {
            return null;
        }

        return Crypt::encryptString(json_encode($value, JSON_UNESCAPED_UNICODE));
    }
}
