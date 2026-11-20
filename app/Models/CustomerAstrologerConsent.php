<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerAstrologerConsent extends Model
{
    use HasFactory;

    public const STATUS_GRANTED = 'granted';
    public const STATUS_DECLINED_SESSION = 'declined_session';
    public const STATUS_WITHDRAWN = 'withdrawn';
    public const STATUS_PENDING = 'pending';

    public const SCOPE_PERSONAL_BIRTH_PROFILE = 'personal_birth_profile';

    protected $table = 'customer_astrologer_consents';

    protected $fillable = [
        'user_id',
        'admin_id',
        'status',
        'scope',
        'consented_at',
        'withdrawn_at',
        'last_session_id',
    ];

    protected $casts = [
        'consented_at' => 'datetime',
        'withdrawn_at' => 'datetime',
        'last_session_id' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    public function lastSession(): BelongsTo
    {
        return $this->belongsTo(ChatSession::class, 'last_session_id');
    }

    public function isGranted(): bool
    {
        return $this->status === self::STATUS_GRANTED;
    }

    public function isDeclinedForSession(?int $sessionId): bool
    {
        if ($this->status !== self::STATUS_DECLINED_SESSION) {
            return false;
        }

        if ($sessionId === null || $this->last_session_id === null) {
            return true;
        }

        return (int) $this->last_session_id === (int) $sessionId;
    }

    public function shouldPromptForSession(?int $sessionId): bool
    {
        if ($this->isGranted()) {
            return false;
        }

        if ($this->isDeclinedForSession($sessionId)) {
            return false;
        }

        return true;
    }
}
