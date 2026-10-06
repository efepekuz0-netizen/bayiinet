<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Source extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'type',
        'url',
        'file_path',
        'mapping',
        'xml_margin_percent', 'min_margin_percent', 'tax_rate', 'prices_include_tax',
        'is_active',
        'priority',
        'last_imported_at',
        'last_product_count',
        'last_error',
    ];

    protected $casts = [
        'mapping' => 'array',
        'xml_margin_percent' => 'decimal:2',
        'min_margin_percent' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'prices_include_tax' => 'boolean',
        'is_active' => 'boolean',
        'priority' => 'integer',
        'last_imported_at' => 'datetime',
        'last_product_count' => 'integer',
    ];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function imports(): HasMany
    {
        return $this->hasMany(XmlImport::class);
    }
}
