<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVariant extends Model
{
    protected $fillable = [
        'product_id',
        'barcode',
        'sku',
        'name',
        'value',
        'variant_price',
        'variant_stock',
        'variant_images',
        'color',
        'stock',
        'price_diff',
        'extra',
    ];

    protected $casts = [
        'variant_price' => 'decimal:2',
        'variant_stock' => 'integer',
        'variant_images' => 'array',
        'stock' => 'integer',
        'price_diff' => 'decimal:2',
        'extra' => 'array',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->name.': '.$this->value);
    }
}
