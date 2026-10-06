<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DealerTrendyolListing extends Model
{
    protected $fillable = [
        'dealer_id',
        'product_id',
        'product_variant_id',
        'barcode',
        'sale_price',
        'list_price',
        'quantity',
        'category_id',
        'brand_id',
        'status',
        'batch_request_id',
        'error',
        'sent_at',
        'checked_at',
    ];

    protected $casts = [
        'sale_price' => 'decimal:2',
        'list_price' => 'decimal:2',
        'quantity' => 'integer',
        'sent_at' => 'datetime',
        'checked_at' => 'datetime',
    ];

    public function dealer(): BelongsTo
    {
        return $this->belongsTo(Dealer::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
