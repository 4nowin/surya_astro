<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Report delivery, snapshot, and lifecycle model.
 *
 * @property int $id
 * @property string $uuid
 * @property int|null $user_id
 * @property int $report_purchase_id
 * @property array<string, mixed>|null $birth_payload
 * @property string $language
 * @property string $ayanamsa_id
 * @property string $ayanamsa_version
 * @property string $calculation_engine_version
 * @property string $interpretation_rule_version
 * @property string $report_template_version
 * @property string $status 'queued', 'processing', 'completed', 'failed'
 * @property string|null $file_path
 * @property string|null $file_hash
 * @property int|null $file_size_bytes
 * @property int|null $page_count
 * @property int|null $generation_duration_ms
 * @property string|null $error_message
 * @property int $download_count
 * @property \Illuminate\Support\Carbon|null $pdf_retained_until
 * @property \Illuminate\Support\Carbon|null $regeneration_eligible_until
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class KundliReport extends Model
{
    use HasFactory;

    public const STATUS_QUEUED = 'queued';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'uuid',
        'user_id',
        'report_purchase_id',
        'birth_payload',
        'language',
        'ayanamsa_id',
        'ayanamsa_version',
        'calculation_engine_version',
        'interpretation_rule_version',
        'report_template_version',
        'status',
        'file_path',
        'file_hash',
        'file_size_bytes',
        'page_count',
        'generation_duration_ms',
        'error_message',
        'download_count',
        'pdf_retained_until',
        'regeneration_eligible_until',
    ];

    protected $attributes = [
        'download_count' => 0,
        'status' => self::STATUS_QUEUED,
        'language' => 'en',
    ];

    protected $casts = [
        'birth_payload' => 'array',
        'file_size_bytes' => 'integer',
        'page_count' => 'integer',
        'generation_duration_ms' => 'integer',
        'download_count' => 'integer',
        'pdf_retained_until' => 'datetime',
        'regeneration_eligible_until' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(ReportPurchase::class, 'report_purchase_id');
    }

    public function isQueued(): bool
    {
        return $this->status === self::STATUS_QUEUED;
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
}
