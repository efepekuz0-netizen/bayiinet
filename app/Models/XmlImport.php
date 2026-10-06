<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class XmlImport extends Model
{
    protected $fillable = [
        'source_id',
        'user_id',
        'file_name',
        'file_path',
        'status',
        'products_created',
        'products_updated',
        'errors',
        'completed_at',
        'total_products',
        'created_count',
        'updated_count',
        'skipped_count',
        'error_count',
        'log',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'products_created' => 'integer',
        'products_updated' => 'integer',
        'total_products' => 'integer',
        'created_count' => 'integer',
        'updated_count' => 'integer',
        'skipped_count' => 'integer',
        'error_count' => 'integer',
        'errors' => 'array',
        'completed_at' => 'datetime',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
