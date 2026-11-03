<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserNotificationPreference extends Model
{
    use HasFactory;

    protected $table = 'user_notification_preferences';

    protected $fillable = [
        'user_id',
        'timezone',
        'master_enabled',
        'panchang_enabled',
        'horoscope_enabled',
        'personalized_enabled',
        'reminders_enabled',
        'preferred_delivery_hour',
    ];

    protected $casts = [
        'master_enabled' => 'boolean',
        'panchang_enabled' => 'boolean',
        'horoscope_enabled' => 'boolean',
        'personalized_enabled' => 'boolean',
        'reminders_enabled' => 'boolean',
        'preferred_delivery_hour' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
