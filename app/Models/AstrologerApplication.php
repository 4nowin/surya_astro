<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AstrologerApplication extends Model
{
    use HasFactory;

    protected $table = 'astrologer_applications';

    protected $fillable = [
        'admin_id',
        'status',
        'image',
        'experience',
        'price',
        'call_price',
        'original_price',
        'chat_minutes',
        'call_minutes',
        'en_name',
        'en_expertise',
        'en_languages',
        'en_excerpt',
        'en_description',
        'hi_name',
        'hi_expertise',
        'hi_languages',
        'hi_excerpt',
        'hi_description',
        'reviewed_by',
        'reviewed_at',
        'rejection_reason',
        'submitted_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
        'submitted_at' => 'datetime',
        'price' => 'integer',
        'call_price' => 'integer',
        'original_price' => 'integer',
        'chat_minutes' => 'integer',
        'call_minutes' => 'integer',
    ];

    public function admin()
    {
        return $this->belongsTo(Admin::class, 'admin_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(Admin::class, 'reviewed_by');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }
}
