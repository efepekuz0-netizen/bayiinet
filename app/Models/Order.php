<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Order extends Model
{
    protected $fillable = [
        'order_number',
        'dealer_id',
        'customer_name',
        'customer_phone',
        'customer_city',
        'status',
        'created_at',
        'customer_district',
        'customer_address',
        'customer_email',
        'subtotal',
        'shipping_cost',
        'total',
        'cargo_company',
        'tracking_number',
        'cargo_pdf_path',
        'cargo_label_uploaded_at',
        'payment_proof_path',
        'admin_note',
        'dealer_note',
        'paid_at',
        'shipped_at',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'total' => 'decimal:2',
        'created_at' => 'datetime',
        'paid_at' => 'datetime',
        'shipped_at' => 'datetime',
        'cargo_label_uploaded_at' => 'datetime',
    ];

    /** @var array<string, array{0: string, 1: string}> [etiket, bootstrap tonu] */
    public const STATUS_LABELS = [
        'pending' => ['Bekliyor', 'secondary'],
        'paid' => ['Ödendi', 'info'],
        'preparing' => ['Hazırlanıyor', 'primary'],
        'shipped' => ['Kargoda', 'warning'],
        'delivered' => ['Teslim edildi', 'success'],
        'cancelled' => ['İptal edildi', 'dark'],
        'returned' => ['İade', 'danger'],
    ];

    protected static function booted(): void
    {
        static::creating(function (Order $order): void {
            if (empty($order->order_number)) {
                $order->order_number = 'BYI-'.Str::upper((string) Str::ulid());
            }
        });
    }

    /** Sipariş durumunun Türkçe etiketi */
    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status][0] ?? (string) $this->status;
    }

    /** Sipariş durumunun Bootstrap rozet tonu */
    public function getStatusToneAttribute(): string
    {
        return self::STATUS_LABELS[$this->status][1] ?? 'secondary';
    }

    public function dealer(): BelongsTo
    {
        return $this->belongsTo(Dealer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function balanceTransaction(): HasOne
    {
        return $this->hasOne(BalanceTransaction::class);
    }
}
