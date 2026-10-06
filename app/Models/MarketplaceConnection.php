<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketplaceConnection extends Model
{
    protected $fillable = [
        'provider',
        'name',
        'account_id',
        'credentials',
        'options',
        'last_connected_at',
        'last_error',
        'orders_cursor',
        'orders_window_start',
        'orders_window_end',
        'orders_has_more',
        'last_orders_synced_at',
    ];

    protected $hidden = [
        'credentials',
        'options',
        'orders_cursor',
    ];

    protected $casts = [
        'credentials' => 'encrypted:array',
        'options' => 'encrypted:array',
        'last_connected_at' => 'datetime',
        'orders_window_start' => 'datetime',
        'orders_window_end' => 'datetime',
        'orders_has_more' => 'boolean',
        'last_orders_synced_at' => 'datetime',
    ];

    public function orders(): HasMany
    {
        return $this->hasMany(MarketplaceOrder::class);
    }

    public function syncRuns(): HasMany
    {
        return $this->hasMany(MarketplaceSyncRun::class);
    }
}
