<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'source_id',
        'category_id',
        'stock_code',
        'barcode',
        'title',
        'slug',
        'brand',
        'description',
        'main_category',
        'sub_category',
        'category_path',
        'price',
        'cost_price',
        'xml_margin_percent',
        'sell_price',
        'min_margin_percent',
        'list_price',
        'tax_rate',
        'desi',
        'stock',
        'is_active',
        'is_featured',
        'show_on_homepage',
        'has_variants',
        'images',
        'attributes',
        'last_synced_at',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'xml_margin_percent' => 'decimal:2',
        'sell_price' => 'decimal:2',
        'min_margin_percent' => 'decimal:2',
        'list_price' => 'decimal:2',
        'tax_rate' => 'integer',
        'desi' => 'decimal:2',
        'stock' => 'integer',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'show_on_homepage' => 'boolean',
        'has_variants' => 'boolean',
        'images' => 'array',
        'attributes' => 'array',
        'last_synced_at' => 'datetime',
    ];

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function getEffectiveStockAttribute(): int
    {
        if ($this->has_variants) {
            return $this->variants()->sum('stock') ?? 0;
        }

        return (int) $this->stock;
    }
}
