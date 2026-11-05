<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Authoritative financial ledger record for detailed report commerce.
 *
 * @property int $id
 * @property string $uuid
 * @property int|null $user_id
 * @property string $idempotency_key
 * @property string $transaction_type 'debit' or 'compensating_refund'
 * @property float $amount
 * @property string $currency
 * @property int|null $related_purchase_id
 * @property string $status 'pending', 'completed', 'failed'
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class ReportPurchase extends Model
{
    use HasFactory;

    public const TYPE_DEBIT = 'debit';
    public const TYPE_REFUND = 'compensating_refund';

    public const STATUS_PENDING = 'pending';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'uuid',
        'user_id',
        'idempotency_key',
        'transaction_type',
        'amount',
        'currency',
        'related_purchase_id',
        'status',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function report(): HasOne
    {
        return $this->hasOne(KundliReport::class, 'report_purchase_id');
    }

    public function originalPurchase(): BelongsTo
    {
        return $this->belongsTo(self::class, 'related_purchase_id');
    }

    public function compensatingRefund(): HasOne
    {
        return $this->hasOne(self::class, 'related_purchase_id');
    }

    public function isDebit(): bool
    {
        return $this->transaction_type === self::TYPE_DEBIT;
    }

    public function isRefund(): bool
    {
        return $this->transaction_type === self::TYPE_REFUND;
    }
}
