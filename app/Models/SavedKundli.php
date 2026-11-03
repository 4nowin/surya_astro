<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavedKundli extends Model
{
    use HasFactory;

    protected $table = 'saved_kundlis';

    protected $fillable = [
        'user_id',
        'profile_id',
        'title',
        'person_name',
        'relationship',
        'canonical_key',
        'date_of_birth',
        'birth_time',
        'birth_time_precision',
        'approximate_time_window',
        'birth_city',
        'latitude',
        'longitude',
        'timezone',
        'chart_data',
        'notes',
    ];

    protected $casts = [
        'date_of_birth' => 'date:Y-m-d',
        'latitude' => 'float',
        'longitude' => 'float',
        'chart_data' => 'array',
    ];

    /**
     * The user who saved this Kundli.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Associated profile if still present.
     */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(UserAstrologyProfile::class, 'profile_id');
    }

    /**
     * Check if the associated profile has changed since this snapshot was saved.
     */
    public function isOutdated(): bool
    {
        if (!$this->profile) {
            return false;
        }

        return $this->canonical_key !== $this->profile->computeCanonicalKey();
    }
}
