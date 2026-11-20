<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jajman Kundli PDF Report model.
 *
 * @property int $id
 * @property int $admin_id
 * @property int $jajman_id
 * @property int|null $user_id
 * @property string $report_type
 * @property string $report_category
 * @property string $tier
 * @property string $language
 * @property string|null $title
 * @property string|null $person_name
 * @property string $file_disk
 * @property string $file_path
 * @property int|null $file_size_bytes
 * @property array<string, mixed> $birth_snapshot
 * @property array<string, mixed>|null $calculation_settings
 * @property array<string, mixed>|null $branding_snapshot
 * @property array<string, mixed>|null $meta_payload
 * @property \Illuminate\Support\Carbon|null $generated_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class JajmanKundliReport extends Model
{
    use HasFactory;

    public const CATEGORY_KUNDLI = 'kundli';
    public const CATEGORY_PANCHANG = 'panchang';
    public const CATEGORY_DAILY_INSIGHTS = 'daily_insights';

    public const TIER_FREE = 'free';
    public const TIER_DETAILED = 'detailed';

    protected $table = 'jajman_kundli_reports';

    protected $fillable = [
        'admin_id',
        'jajman_id',
        'user_id',
        'report_type',
        'report_category',
        'tier',
        'language',
        'title',
        'person_name',
        'file_disk',
        'file_path',
        'file_size_bytes',
        'birth_snapshot',
        'calculation_settings',
        'branding_snapshot',
        'meta_payload',
        'generated_at',
    ];

    protected $casts = [
        'birth_snapshot' => 'array',
        'calculation_settings' => 'array',
        'branding_snapshot' => 'array',
        'meta_payload' => 'array',
        'file_size_bytes' => 'integer',
        'generated_at' => 'datetime',
    ];

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'admin_id');
    }

    public function jajman(): BelongsTo
    {
        return $this->belongsTo(Jajman::class, 'jajman_id')->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
