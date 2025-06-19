<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Spatie\Permission\Traits\HasPermissions;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Laravel\Sanctum\HasApiTokens;
use App\Models\Review;
use App\Models\ChatSession;
use App\Models\Chat;
use Illuminate\Support\Str;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable, HasRoles, HasPermissions, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'profile_image',
        'name',
        'language',
        'wallet_balance',
        'email',
        'phone',
        'gender',
        'birth_time',
        'date_of_birth',
        'place_of_birth',
        'country',
        'role',
        'status',
        'email_verified_at',
        'password',
        'google_id',
        'facebook_id',
        'device_token',
        'fcm_token',
        'referral_code',
        'referred_by',
        'premium_started_at',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }
    public function getJWTCustomClaims()
    {
        return [];
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function chatSessions()
    {
        return $this->hasMany(ChatSession::class);
    }

    public function chats()
    {
        return $this->hasMany(Chat::class);
    }

    public function chatPayments()
    {
        return $this->hasMany(ChatPayment::class);
    }

    public static function generateReferralCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (self::where('referral_code', $code)->exists());

        return $code;
    }
}
