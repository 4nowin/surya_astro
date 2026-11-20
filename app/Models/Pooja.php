<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pooja extends Model
{
    use HasFactory;

    protected $fillable = [
        'admin_id',
        'title',
        'image',
        'tag',
        'language',
        'excerpt',
        'description',
        'start_date',
        'end_date',
        'price',
        'original_price',
        'active',
        'home_priority',
    ];

    protected $appends = [
        'image_url',
        'provider_name',
    ];

    public function admin()
    {
        return $this->belongsTo(Admin::class, 'admin_id', 'id');
    }

    public function getImageUrlAttribute(): ?string
    {
        $img = $this->image;
        if (empty($img)) {
            return null;
        }

        if (str_starts_with($img, 'http://') || str_starts_with($img, 'https://')) {
            return $img;
        }

        if (str_starts_with($img, 'storage/uploads/') || str_starts_with($img, '/storage/uploads/')) {
            $clean = ltrim($img, '/');
            return asset($clean);
        }

        if (str_starts_with($img, 'storage/') || str_starts_with($img, '/storage/')) {
            $clean = ltrim($img, '/');
            return asset($clean);
        }

        return asset('storage/uploads/' . ltrim($img, '/'));
    }

    public function getProviderNameAttribute(): ?string
    {
        return $this->resolveProviderName($this->language);
    }

    /**
     * Resolve provider name from astrologers, public profile, or admin.
     *
     * @param string|null $lang
     * @return string|null
     */
    public function resolveProviderName(?string $lang = null): ?string
    {
        if (empty($this->admin_id)) {
            return null;
        }

        $targetLang = $lang ?: ($this->language ?: 'en');

        if ($this->relationLoaded('admin') && $this->admin) {
            $admin = $this->admin;
            if ($admin->relationLoaded('astrologers') && $admin->astrologers->isNotEmpty()) {
                // 1. In target language & active
                $matched = $admin->astrologers->first(function ($astro) use ($targetLang) {
                    return $astro->language === $targetLang && (int)$astro->active === 1 && !empty(trim($astro->name ?? ''));
                });
                if ($matched) {
                    return trim($matched->name);
                }

                // 2. Any active
                $active = $admin->astrologers->first(function ($astro) {
                    return (int)$astro->active === 1 && !empty(trim($astro->name ?? ''));
                });
                if ($active) {
                    return trim($active->name);
                }

                // 3. Any with name
                $any = $admin->astrologers->first(function ($astro) {
                    return !empty(trim($astro->name ?? ''));
                });
                if ($any) {
                    return trim($any->name);
                }
            }

            if ($admin->relationLoaded('publicProfile') && $admin->publicProfile && !empty(trim($admin->publicProfile->display_name ?? ''))) {
                return trim($admin->publicProfile->display_name);
            }

            if (!empty(trim($admin->name ?? ''))) {
                return trim($admin->name);
            }
        }

        // Fallback when relations are not preloaded
        // 1. Active astrologer in target language
        $astrologer = Astrologer::where('admin_id', $this->admin_id)
            ->where('language', $targetLang)
            ->where('active', 1)
            ->first();
        if ($astrologer && !empty(trim($astrologer->name ?? ''))) {
            return trim($astrologer->name);
        }

        // 2. Any active astrologer for this admin_id
        $fallback = Astrologer::where('admin_id', $this->admin_id)
            ->where('active', 1)
            ->first();
        if ($fallback && !empty(trim($fallback->name ?? ''))) {
            return trim($fallback->name);
        }

        // 3. Any astrologer row for this admin_id
        $any = Astrologer::where('admin_id', $this->admin_id)->first();
        if ($any && !empty(trim($any->name ?? ''))) {
            return trim($any->name);
        }

        // 4. ProfessionalPublicProfile
        $profile = ProfessionalPublicProfile::where('admin_id', $this->admin_id)->first();
        if ($profile && !empty(trim($profile->display_name ?? ''))) {
            return trim($profile->display_name);
        }

        // 5. Admin
        $admin = Admin::find($this->admin_id);
        if ($admin && !empty(trim($admin->name ?? ''))) {
            return trim($admin->name);
        }

        return null;
    }
}
