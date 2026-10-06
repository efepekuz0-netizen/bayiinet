<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceOrder extends Model
{
    protected $fillable = [
        'marketplace_connection_id',
        'remote_package_id',
        'order_number',
        'status',
        'cargo_company',
        'cargo_tracking_number',
        'cargo_tracking_link',
        'invoice_number',
        'total_amount',
        'item_count',
        'ordered_at',
        'last_modified_at',
        'payload',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'item_count' => 'integer',
        'ordered_at' => 'datetime',
        'last_modified_at' => 'datetime',
        'payload' => 'encrypted:array',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(MarketplaceConnection::class, 'marketplace_connection_id');
    }
}
