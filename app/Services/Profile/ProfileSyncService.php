<?php

namespace App\Services\Profile;

use App\Models\Astrologer;
use App\Models\AstrologerApplication;
use App\Models\ProfessionalPublicProfile;
use Illuminate\Support\Facades\Log;

class ProfileSyncService
{
    /**
     * Synchronize updates from ProfessionalPublicProfile to published Astrologer records.
     * Enforces superadmin moderation rules: only updates existing published astrologers;
     * never creates new astrologer records out of nowhere.
     */
    public function syncFromPublicProfile(ProfessionalPublicProfile $profile): void
    {
        $adminId = $profile->admin_id;

        // Fetch published listings for this practitioner
        $publishedListings = Astrologer::where('admin_id', $adminId)->get();

        if ($publishedListings->isEmpty()) {
            // Not published in Astro Setu; do NOT auto-publish
            return;
        }

        // Harmonize types
        $experienceFormatted = null;
        if ($profile->years_of_experience !== null) {
            $years = (int) $profile->years_of_experience;
            $experienceFormatted = $years . ($years === 1 ? ' year' : ' years');
        }

        $languagesStr = null;
        if (is_array($profile->languages) && !empty($profile->languages)) {
            $languagesStr = implode(', ', array_map('trim', $profile->languages));
        }

        $specializationsStr = null;
        if (is_array($profile->specializations) && !empty($profile->specializations)) {
            $specializationsStr = implode(', ', array_map('trim', $profile->specializations));
        }

        foreach ($publishedListings as $listing) {
            $isEn = ($listing->language === 'en');
            $isHi = ($listing->language === 'hi');

            $updates = [];

            // 1. Display name -> name
            if (!empty($profile->display_name)) {
                if ($isEn) {
                    $updates['name'] = $profile->display_name;
                } elseif ($isHi) {
                    // Only update Hindi name if it was empty
                    if (empty($listing->name)) {
                        $updates['name'] = $profile->display_name;
                    }
                }
            }

            // 2. Avatar / photo URL -> image
            if (!empty($profile->profile_photo_url)) {
                $updates['image'] = $profile->profile_photo_url;
            }

            // 3. About -> description
            if (!empty($profile->about)) {
                if ($isEn) {
                    $updates['description'] = $profile->about;
                } elseif ($isHi && empty($listing->description)) {
                    $updates['description'] = $profile->about;
                }
            }

            // 4. Headline -> excerpt
            if (!empty($profile->headline)) {
                if ($isEn) {
                    $updates['excerpt'] = $profile->headline;
                } elseif ($isHi && empty($listing->excerpt)) {
                    $updates['excerpt'] = $profile->headline;
                }
            }

            // 5. Experience
            if (!empty($experienceFormatted)) {
                $updates['experience'] = $experienceFormatted;
            }

            // 6. Languages
            if (!empty($languagesStr)) {
                if ($isEn) {
                    $updates['astrologer_language'] = $languagesStr;
                } elseif ($isHi && empty($listing->astrologer_language)) {
                    $updates['astrologer_language'] = $languagesStr;
                }
            }

            // 7. Specializations -> expertise
            if (!empty($specializationsStr)) {
                if ($isEn) {
                    $updates['expertise'] = $specializationsStr;
                } elseif ($isHi && empty($listing->expertise)) {
                    $updates['expertise'] = $specializationsStr;
                }
            }

            if (!empty($updates)) {
                $listing->update($updates);
            }
        }
    }

    /**
     * Synchronize from approved AstrologerApplication into ProfessionalPublicProfile.
     * Prevents empty or null values from clobbering existing public profile data.
     */
    public function syncFromApplication(AstrologerApplication $application, bool $force = false): void
    {
        if (!$force && !$application->isApproved()) {
            return;
        }

        $adminId = $application->admin_id;
        $profile = ProfessionalPublicProfile::firstOrCreate(
            ['admin_id' => $adminId],
            [
                'display_name' => $application->en_name ?? 'Practitioner',
                'slug' => ProfessionalPublicProfile::generateUniqueSlug($application->en_name ?? 'practitioner', $adminId),
                'headline' => $application->en_excerpt ?? 'Professional Spiritual & Vedic Consultant',
                'about' => $application->en_description,
                'categories' => ['astrologer'],
                'specializations' => $this->parseCommaSeparatedArray($application->en_expertise),
                'languages' => $this->parseCommaSeparatedArray($application->en_languages),
                'years_of_experience' => $this->parseYearsFromExperience($application->experience),
                'profile_photo_url' => $application->image,
                'is_online_available' => true,
                'is_in_person_available' => false,
                'is_home_visit_available' => false,
                'is_published' => false,
                'is_accepting_bookings' => true,
                'is_email_mandatory' => false,
                'profile_status' => 'not_published',
            ]
        );

        $updates = [];

        if (!empty($application->en_name)) {
            $updates['display_name'] = $application->en_name;
        }

        if (!empty($application->image)) {
            $updates['profile_photo_url'] = $application->image;
        }

        if (!empty($application->en_description)) {
            $updates['about'] = $application->en_description;
        }

        if (!empty($application->en_excerpt)) {
            $updates['headline'] = $application->en_excerpt;
        }

        if (!empty($application->experience)) {
            $years = $this->parseYearsFromExperience($application->experience);
            if ($years !== null) {
                $updates['years_of_experience'] = $years;
            }
        }

        if (!empty($application->en_languages)) {
            $langs = $this->parseCommaSeparatedArray($application->en_languages);
            if (!empty($langs)) {
                $updates['languages'] = $langs;
            }
        }

        if (!empty($application->en_expertise)) {
            $specs = $this->parseCommaSeparatedArray($application->en_expertise);
            if (!empty($specs)) {
                $updates['specializations'] = $specs;
            }
        }

        if (!empty($updates)) {
            $profile->update($updates);
        }
    }

    /**
     * Parse years of experience integer from various string representations.
     * E.g. "10 years", "10+ Years", "5", "1 year" -> 10, 10, 5, 1.
     */
    public function parseYearsFromExperience(?string $experience): ?int
    {
        if ($experience === null || trim($experience) === '') {
            return null;
        }

        if (preg_match('/(\d+)/', $experience, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    /**
     * Parse a comma-separated string into a clean trimmed array.
     */
    public function parseCommaSeparatedArray(?string $string): array
    {
        if (empty($string)) {
            return [];
        }

        $items = array_map('trim', explode(',', $string));
        return array_values(array_filter($items, fn($item) => $item !== ''));
    }

    /**
     * Harmonize experience into both integer years and formatted string.
     */
    public function harmonizeExperience(?string $string, ?int $years): array
    {
        if ($years === null && $string !== null) {
            $years = $this->parseYearsFromExperience($string);
        }

        $formatted = $string;
        if ($years !== null) {
            $formatted = "{$years} " . ($years === 1 ? 'year' : 'years');
        }

        return [
            'years' => $years,
            'formatted' => $formatted,
        ];
    }
}
