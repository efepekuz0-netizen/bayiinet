<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Dealer extends Model
{
    protected $fillable = [
        'user_id',
        'company_name',
        'tax_number',
        'tax_office',
        'phone',
        'address',
        'city',
        'status',
        'balance',
        'approved_at',
        'suspended_at',
        'district',
        'xml_token',
        'integration_api_key',
        'auto_sync_enabled',
        'last_synced_at',
        'default_marketplace_margin',
        'admin_note',
        'trendyol_seller_id',
        'trendyol_credentials',
        'trendyol_last_error',
    ];

    protected $hidden = [
        'trendyol_credentials',
    ];

    protected $casts = [
        'trendyol_credentials' => 'encrypted:array',
        'balance' => 'decimal:2',
        'default_marketplace_margin' => 'decimal:2',
        'auto_sync_enabled' => 'boolean',
        'last_synced_at' => 'datetime',
        'approved_at' => 'datetime',
        'suspended_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function balanceTransactions(): HasMany
    {
        return $this->hasMany(BalanceTransaction::class);
    }

    public function trendyolListings(): HasMany
    {
        return $this->hasMany(DealerTrendyolListing::class);
    }

    public function hasTrendyolCredentials(): bool
    {
        $credentials = $this->trendyol_credentials ?? [];

        return filled($this->trendyol_seller_id)
            && filled($credentials['api_key'] ?? null)
            && filled($credentials['api_secret'] ?? null);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
