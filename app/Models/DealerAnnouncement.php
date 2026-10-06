<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DealerAnnouncement extends Model
{
    protected $fillable = [
        'title',
        'content',
        'priority',
        'active_from',
        'active_until',
        'created_by',
        'body',
        'is_active',
        'starts_at',
        'ends_at',
    ];

    protected $casts = [
        'priority' => 'integer',
        'active_from' => 'datetime',
        'active_until' => 'datetime',
        'is_active' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
