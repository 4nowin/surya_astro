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
     * The guard name for Spatie Laravel Permission.
     *
     * @var string
     */
    protected $guard_name = 'admin';

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

    protected $appends = [
        'avatar_url',
    ];

    public function getAvatarUrlAttribute(): ?string
    {
        // 1. Check Astrologer profile image
        if ($this->relationLoaded('astrologer') && $this->astrologer?->image_url) {
            return $this->astrologer->image_url;
        }

        // 2. Check public profile photo
        if ($this->relationLoaded('publicProfile') && $this->publicProfile?->profile_photo_url) {
            $photo = $this->publicProfile->profile_photo_url;
            if (str_starts_with($photo, 'http://') || str_starts_with($photo, 'https://')) {
                return $photo;
            }
            $clean = ltrim(preg_replace('#^/?storage/#', '', $photo), '/');
            return \Illuminate\Support\Facades\Storage::disk('public')->url($clean);
        }

        // Fallback to query
        $astro = $this->astrologer()->first();
        if ($astro?->image_url) {
            return $astro->image_url;
        }

        $app = $this->astrologerApplication()->first();
        if ($app?->image_url) {
            return $app->image_url;
        }

        $pub = $this->publicProfile()->first();
        if ($pub?->profile_photo_url) {
            $photo = $pub->profile_photo_url;
            if (str_starts_with($photo, 'http://') || str_starts_with($photo, 'https://')) {
                return $photo;
            }
            $clean = ltrim(preg_replace('#^/?storage/#', '', $photo), '/');
            return \Illuminate\Support\Facades\Storage::disk('public')->url($clean);
        }

        return null;
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasAnyRole(['Super Admin', 'Admin'], 'admin');
    }

    public function isPractitioner(): bool
    {
        return $this->hasRole('Astrologer', 'admin');
    }

    public function astrologer()
    {
        return $this->hasOne(Astrologer::class);
    }

    public function astrologers()
    {
        return $this->hasMany(Astrologer::class);
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class, 'admin_id');
    }

    public function availabilities()
    {
        return $this->hasMany(ProfessionalAvailability::class, 'admin_id');
    }

    public function services()
    {
        return $this->hasMany(ProfessionalService::class, 'admin_id');
    }

    public function transactions()
    {
        return $this->hasMany(ProfessionalTransaction::class, 'admin_id');
    }

    public function invoices()
    {
        return $this->hasMany(ProfessionalInvoice::class, 'admin_id');
    }

    public function documentSetting()
    {
        return $this->hasOne(ProfessionalDocumentSetting::class, 'admin_id');
    }

    public function availabilityExceptions()
    {
        return $this->hasMany(ProfessionalAvailabilityException::class, 'admin_id');
    }

    public function publicProfile()
    {
        return $this->hasOne(ProfessionalPublicProfile::class, 'admin_id');
    }

    public function bookingRequests()
    {
        return $this->hasMany(ProfessionalBookingRequest::class, 'admin_id');
    }

    public function astrologerApplication()
    {
        return $this->hasOne(AstrologerApplication::class, 'admin_id')->latestOfMany();
    }

    public function astrologerApplications()
    {
        return $this->hasMany(AstrologerApplication::class, 'admin_id')->orderBy('id', 'desc');
    }
}
