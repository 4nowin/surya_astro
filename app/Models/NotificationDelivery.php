<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationDelivery extends Model
{
    use HasFactory;

    protected $table = 'notification_deliveries';

    protected $fillable = [
        'user_id',
        'user_device_id',
        'notification_type',
        'scheduled_date',
        'scheduled_hour',
        'status',
        'fcm_message_id',
        'error_message',
        'sent_at',
    ];

    protected $casts = [
        'scheduled_date' => 'date',
        'scheduled_hour' => 'integer',
        'sent_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(UserDevice::class, 'user_device_id');
    }
}
