<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceSyncRun extends Model
{
    protected $fillable = [
        'marketplace_connection_id',
        'source_id',
        'sync_type',
        'status',
        'total_count',
        'matched_count',
        'skipped_count',
        'failed_count',
        'batch_request_ids',
        'errors',
        'message',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'total_count' => 'integer',
        'matched_count' => 'integer',
        'skipped_count' => 'integer',
        'failed_count' => 'integer',
        'batch_request_ids' => 'array',
        'errors' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(MarketplaceConnection::class, 'marketplace_connection_id');
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }
}
