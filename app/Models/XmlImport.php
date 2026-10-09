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

    /** @var array<string, array{0: string, 1: string}> */
    public const STATUS_LABELS = [
        'pending' => ['Bekliyor', 'secondary'],
        'processing' => ['İşleniyor', 'info'],
        'completed' => ['Tamamlandı', 'success'],
        'failed' => ['Başarısız', 'danger'],
    ];

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status][0] ?? (string) $this->status;
    }

    public function getStatusToneAttribute(): string
    {
        return self::STATUS_LABELS[$this->status][1] ?? 'secondary';
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
