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
        'errors',
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
        'total_products' => 'integer',
        'created_count' => 'integer',
        'updated_count' => 'integer',
        'skipped_count' => 'integer',
        'error_count' => 'integer',
        'errors' => 'array',
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
