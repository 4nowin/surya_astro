<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserAstrologyProfile extends Model
{
    use HasFactory;

    public const PRECISION_VERIFIED = 'verified';
    public const PRECISION_APPROXIMATE = 'approximate';
    public const PRECISION_UNKNOWN = 'unknown';

    public const PRECISIONS = [
        self::PRECISION_VERIFIED,
        self::PRECISION_APPROXIMATE,
        self::PRECISION_UNKNOWN,
    ];

    /**
     * Standard approximate time windows for Vedic astrology profile intake.
     */
    public const APPROXIMATE_WINDOWS = [
        'early_morning' => 'Early Morning (04:00 - 08:00)',
        'morning'       => 'Morning (08:00 - 12:00)',
        'afternoon'     => 'Afternoon (12:00 - 16:00)',
        'evening'       => 'Evening (16:00 - 20:00)',
        'night'         => 'Night (20:00 - 00:00)',
        'late_night'    => 'Late Night (00:00 - 04:00)',
    ];

    public const VALID_TIME_WINDOWS = [
        'early_morning',
        'morning',
        'afternoon',
        'evening',
        'night',
        'late_night',
    ];

    protected $table = 'user_astrology_profiles';

    protected $fillable = [
        'user_id',
        'name',
        'relationship',
        'is_default',
        'date_of_birth',
        'birth_time_precision',
        'birth_time',
        'approximate_time_window',
        'birth_city',
        'birth_state',
        'birth_country',
        'latitude',
        'longitude',
        'timezone',
    ];

    protected $casts = [
        'date_of_birth' => 'date:Y-m-d',
        'is_default'    => 'boolean',
        'latitude'      => 'float',
        'longitude'     => 'float',
    ];

    protected $appends = [
        'can_calculate_exact_chart',
        'can_calculate_lagna',
        'precision_label',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isVerifiedPrecision(): bool
    {
        return $this->birth_time_precision === self::PRECISION_VERIFIED;
    }

    public function isApproximatePrecision(): bool
    {
        return $this->birth_time_precision === self::PRECISION_APPROXIMATE;
    }

    public function isUnknownPrecision(): bool
    {
        return $this->birth_time_precision === self::PRECISION_UNKNOWN;
    }

    /**
     * Calculation safeguard: exact birth charts require verified birth time.
     */
    public function getCanCalculateExactChartAttribute(): bool
    {
        return $this->isVerifiedPrecision() && !empty($this->birth_time);
    }

    /**
     * Calculation safeguard: Lagna (Ascendant) requires verified birth time.
     */
    public function getCanCalculateLagnaAttribute(): bool
    {
        return $this->isVerifiedPrecision() && !empty($this->birth_time);
    }

    public function getPrecisionLabelAttribute(): string
    {
        return match ($this->birth_time_precision) {
            self::PRECISION_VERIFIED    => 'Verified Birth Time',
            self::PRECISION_APPROXIMATE => 'Approximate Time Window',
            self::PRECISION_UNKNOWN     => 'Unknown Birth Time (Date Only)',
            default                     => 'Unspecified',
        };
    }
}
