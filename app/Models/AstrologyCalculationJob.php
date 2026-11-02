<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AstrologyCalculationJob extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    protected $table = 'astrology_calculation_jobs';

    protected $fillable = [
        'job_id',
        'calculation_type',
        'canonical_key',
        'payload',
        'status',
        'attempts',
        'max_attempts',
        'worker_id',
        'lease_expires_at',
        'started_at',
        'completed_at',
        'result_cache_key',
        'error_message',
        'error_context',
        'user_id',
    ];

    protected $casts = [
        'payload' => 'array',
        'error_context' => 'array',
        'lease_expires_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'attempts' => 'integer',
        'max_attempts' => 'integer',
    ];

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isProcessing(): bool
    {
        return $this->status === self::STATUS_PROCESSING;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function isStale(): bool
    {
        if ($this->status !== self::STATUS_PROCESSING) {
            return false;
        }

        if ($this->lease_expires_at === null) {
            return true;
        }

        return $this->lease_expires_at->isPast();
    }

    public function canRetry(): bool
    {
        return $this->attempts < $this->max_attempts;
    }

    /**
     * Scope to find jobs eligible for worker claiming:
     * - Pending jobs whose attempts < max_attempts
     * - OR Stale processing jobs whose lease has expired and attempts < max_attempts
     */
    public function scopeClaimable(Builder $query): Builder
    {
        return $query->where(function (Builder $sub) {
            $sub->where('status', self::STATUS_PENDING)
                ->whereColumn('attempts', '<', 'max_attempts');
        })->orWhere(function (Builder $sub) {
            $sub->where('status', self::STATUS_PROCESSING)
                ->where('lease_expires_at', '<', now())
                ->whereColumn('attempts', '<', 'max_attempts');
        });
    }
}
