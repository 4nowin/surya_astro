<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Spatie\Permission\Traits\HasPermissions;
use Laravel\Sanctum\HasApiTokens;
use App\Models\Astrologer;

class Admin extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles, HasPermissions, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_online',
        'is_typing',
        'active',
        'fcm_token',
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

    public function astrologer()
    {
        return $this->hasOne(Astrologer::class);
    }

    public function astrologers()
    {
        return $this->hasMany(Astrologer::class);
    }
}
