<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ProfessionalPublicProfile extends Model
{
    use HasFactory;

    protected $table = 'professional_public_profiles';

    protected $fillable = [
        'admin_id',
        'slug',
        'display_name',
        'profile_photo_url',
        'cover_image_url',
        'headline',
        'about',
        'categories',
        'specializations',
        'languages',
        'years_of_experience',
        'qualifications',
        'city',
        'service_area',
        'is_online_available',
        'is_in_person_available',
        'is_home_visit_available',
        'public_phone',
        'public_email',
        'website',
        'public_address',
        'is_published',
        'is_accepting_bookings',
        'is_email_mandatory',
        'profile_status',
    ];

    protected $casts = [
        'categories' => 'array',
        'specializations' => 'array',
        'languages' => 'array',
        'years_of_experience' => 'integer',
        'is_online_available' => 'boolean',
        'is_in_person_available' => 'boolean',
        'is_home_visit_available' => 'boolean',
        'is_published' => 'boolean',
        'is_accepting_bookings' => 'boolean',
        'is_email_mandatory' => 'boolean',
    ];

    public function admin()
    {
        return $this->belongsTo(Admin::class, 'admin_id');
    }

    /**
     * Check if the profile is currently active and visible to the public.
     */
    public function isLive(): bool
    {
        return (bool) $this->is_published && $this->profile_status === 'published';
    }

    /**
     * Generate an URL-safe unique slug for a practitioner.
     */
    public static function generateUniqueSlug(string $name, int $adminId): string
    {
        $base = Str::slug(trim($name));
        if (empty($base)) {
            $base = 'practitioner-' . $adminId;
        }

        $slug = $base;
        $count = 1;

        while (static::where('slug', $slug)->where('admin_id', '!=', $adminId)->exists()) {
            $slug = "{$base}-{$count}";
            $count++;
        }

        return $slug;
    }

    /**
     * Return public-safe view of this profile (excludes internal admin details).
     */
    public function toPublicArray(): array
    {
        return [
            'slug' => $this->slug,
            'display_name' => $this->display_name,
            'profile_photo_url' => $this->profile_photo_url,
            'cover_image_url' => $this->cover_image_url,
            'headline' => $this->headline,
            'about' => $this->about,
            'categories' => $this->categories ?? [],
            'specializations' => $this->specializations ?? [],
            'languages' => $this->languages ?? [],
            'years_of_experience' => $this->years_of_experience,
            'qualifications' => $this->qualifications,
            'city' => $this->city,
            'service_area' => $this->service_area,
            'consultation_modes' => [
                'online' => (bool) $this->is_online_available,
                'in_person' => (bool) $this->is_in_person_available,
                'home_visit' => (bool) $this->is_home_visit_available,
            ],
            'contact' => [
                'phone' => $this->public_phone,
                'email' => $this->public_email,
                'website' => $this->website,
                'address' => $this->public_address,
            ],
            'is_accepting_bookings' => (bool) $this->is_accepting_bookings,
            'is_email_mandatory' => (bool) $this->is_email_mandatory,
            'profile_status' => $this->profile_status,
        ];
    }
}
