<?php

declare(strict_types=1);

namespace App\Services\Jajman;

use App\Models\ChatSession;
use App\Models\Jajman;
use App\Models\JajmanBirthDetail;
use App\Models\User;
use App\Models\UserAstrologyProfile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class JajmanAutoLinkService
{
    /**
     * Ensure a Jajman record exists and is linked to the practitioner's CRM for a chat session.
     * Idempotent: safe to invoke repeatedly on message sending or session initiation.
     */
    public function ensureJajmanForSession(ChatSession $session): ?Jajman
    {
        if (!$session->user_id || !$session->admin_id) {
            return null;
        }

        $user = $session->user ?? User::find($session->user_id);
        if (!$user) {
            return null;
        }

        return $this->ensureJajmanForUserAndAdmin($user, (int) $session->admin_id);
    }

    /**
     * Ensure a Jajman record exists and is linked for a specific User and Admin (practitioner).
     */
    public function ensureJajmanForUserAndAdmin(User $user, int $adminId): Jajman
    {
        return DB::transaction(function () use ($user, $adminId) {
            $userPhoneNorm = ContactNormalization::normalizePhone($user->phone ?? $user->mobile ?? null);
            $userEmailNorm = ContactNormalization::normalizeEmail($user->email ?? null);

            // Phase 1: Check if this user is ALREADY linked in this practitioner's CRM
            $alreadyLinked = Jajman::whereHas('professionals', function ($q) use ($adminId) {
                $q->where('admins.id', $adminId);
            })
                ->where('user_id', $user->id)
                ->first();

            if ($alreadyLinked) {
                // Check if other candidate Jajmans in this practitioner's CRM have matching phone/email
                $otherDuplicates = $this->findPotentialDuplicateJajmans(
                    $adminId,
                    $alreadyLinked->id,
                    $userPhoneNorm,
                    $userEmailNorm
                );

                if ($otherDuplicates->isNotEmpty()) {
                    $candidateIds = $otherDuplicates->pluck('id')->values()->all();
                    $this->updateDuplicatePivotFlags($adminId, $alreadyLinked->id, true, $candidateIds);
                    $alreadyLinked->setAttribute('has_possible_duplicates', true);
                    $alreadyLinked->setAttribute('possible_duplicate_ids', $candidateIds);
                }

                $this->syncUserBirthDetailIfMissing($alreadyLinked, $user);
                return $alreadyLinked;
            }

            // Phase 2: Not yet linked to user_id in this practitioner's CRM.
            // Look for matching Jajmans in this practitioner's practice by normalized phone or email.
            $candidates = $this->findPotentialDuplicateJajmans(
                $adminId,
                0, // No excluded ID
                $userPhoneNorm,
                $userEmailNorm
            );

            // Case A: Exactly 1 unique match found in the astrologer's practice
            if ($candidates->count() === 1) {
                /** @var Jajman $matchedJajman */
                $matchedJajman = $candidates->first();

                // Associate user_id if null (converting offline manual record to linked)
                if ($matchedJajman->user_id === null) {
                    $matchedJajman->user_id = $user->id;
                    if (empty($matchedJajman->profile_photo) && !empty($user->profile_image)) {
                        $matchedJajman->profile_photo = $user->profile_image;
                    }
                    $matchedJajman->save();
                }

                // Update pivot source and clear duplicate flags
                DB::table('professional_jajmans')
                    ->where('admin_id', $adminId)
                    ->where('jajman_id', $matchedJajman->id)
                    ->update([
                        'source' => 'linked',
                        'has_possible_duplicates' => false,
                        'duplicate_candidates' => null,
                        'updated_at' => now(),
                    ]);

                $matchedJajman->setAttribute('has_possible_duplicates', false);
                $matchedJajman->setAttribute('possible_duplicate_ids', []);

                $this->syncUserBirthDetailIfMissing($matchedJajman, $user);
                return $matchedJajman;
            }

            // Resolve supplementary profile attributes if missing on users row
            $userProfile = UserAstrologyProfile::where('user_id', $user->id)
                ->where('is_default', true)
                ->first() ?? UserAstrologyProfile::where('user_id', $user->id)->first();

            $dateOfBirth = $user->date_of_birth ?? $userProfile?->date_of_birth;
            $city = $user->city ?? $user->place_of_birth ?? $userProfile?->birth_city;
            $gender = $user->gender ?? null;

            // Case B: Ambiguous match (2 or more candidate Jajmans for that astrologer)
            // Do NOT blindly merge; create a new Jajman and flag possible duplicates on all candidate records.
            if ($candidates->count() >= 2) {
                $candidateIds = $candidates->pluck('id')->values()->all();

                $newJajman = Jajman::create([
                    'name' => $user->name ?? 'Astro Setu Client',
                    'phone' => $user->phone ?? $user->mobile ?? null,
                    'email' => $user->email ?? null,
                    'gender' => $gender,
                    'date_of_birth' => $dateOfBirth,
                    'profile_photo' => $user->profile_image ?? null,
                    'city' => $city,
                    'user_id' => $user->id,
                ]);

                // Attach to practitioner with duplicate flags
                DB::table('professional_jajmans')->insert([
                    'admin_id' => $adminId,
                    'jajman_id' => $newJajman->id,
                    'status' => 'active',
                    'source' => 'chat',
                    'has_possible_duplicates' => true,
                    'duplicate_candidates' => json_encode($candidateIds),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Also update the matching candidates' pivots so practitioner sees duplicate alerts on them too
                foreach ($candidates as $c) {
                    $this->addDuplicateCandidateToJajman($adminId, $c->id, $newJajman->id);
                }

                $newJajman->setAttribute('has_possible_duplicates', true);
                $newJajman->setAttribute('possible_duplicate_ids', $candidateIds);

                $this->syncUserBirthDetailIfMissing($newJajman, $user);
                return $newJajman;
            }

            // Case C: 0 matches found in this practitioner's practice (brand new client for this practitioner)
            // Check if user already has a global Jajman record from another practitioner
            $existingGlobal = Jajman::where('user_id', $user->id)->first();

            if ($existingGlobal) {
                $jajmanToLink = $existingGlobal;
                if (empty($jajmanToLink->date_of_birth) && !empty($dateOfBirth)) {
                    $jajmanToLink->date_of_birth = $dateOfBirth;
                    $jajmanToLink->save();
                }
            } else {
                $jajmanToLink = Jajman::create([
                    'name' => $user->name ?? 'Astro Setu Client',
                    'phone' => $user->phone ?? $user->mobile ?? null,
                    'email' => $user->email ?? null,
                    'gender' => $gender,
                    'date_of_birth' => $dateOfBirth,
                    'profile_photo' => $user->profile_image ?? null,
                    'city' => $city,
                    'user_id' => $user->id,
                ]);
            }

            // Link to practitioner
            DB::table('professional_jajmans')->insert([
                'admin_id' => $adminId,
                'jajman_id' => $jajmanToLink->id,
                'status' => 'active',
                'source' => 'chat',
                'has_possible_duplicates' => false,
                'duplicate_candidates' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $jajmanToLink->setAttribute('has_possible_duplicates', false);
            $jajmanToLink->setAttribute('possible_duplicate_ids', []);

            $this->syncUserBirthDetailIfMissing($jajmanToLink, $user);
            return $jajmanToLink;
        });
    }

    /**
     * Find Jajmans in a practitioner's practice that match the normalized phone or email.
     * Excludes the specified Jajman ID if greater than 0.
     *
     * @return Collection<int, Jajman>
     */
    public function findPotentialDuplicateJajmans(
        int $adminId,
        int $excludeJajmanId,
        ?string $normalizedPhone,
        ?string $normalizedEmail
    ): Collection {
        if ($normalizedPhone === null && $normalizedEmail === null) {
            return collect([]);
        }

        $allPractitionerJajmans = Jajman::whereHas('professionals', function ($q) use ($adminId) {
            $q->where('admins.id', $adminId);
        })
            ->when($excludeJajmanId > 0, fn($q) => $q->where('id', '!=', $excludeJajmanId))
            ->get();

        return $allPractitionerJajmans->filter(function (Jajman $jajman) use ($normalizedPhone, $normalizedEmail) {
            // Check normalized phone match
            if ($normalizedPhone !== null && !empty($jajman->phone)) {
                $jPhoneNorm = ContactNormalization::normalizePhone($jajman->phone);
                if ($jPhoneNorm !== null && $jPhoneNorm === $normalizedPhone) {
                    return true;
                }
            }

            // Check normalized email match
            if ($normalizedEmail !== null && !empty($jajman->email)) {
                $jEmailNorm = ContactNormalization::normalizeEmail($jajman->email);
                if ($jEmailNorm !== null && $jEmailNorm === $normalizedEmail) {
                    return true;
                }
            }

            return false;
        })->values();
    }

    /**
     * Update duplicate flags in the professional_jajmans pivot.
     */
    protected function updateDuplicatePivotFlags(
        int $adminId,
        int $jajmanId,
        bool $hasDuplicates,
        array $candidateIds
    ): void {
        DB::table('professional_jajmans')
            ->where('admin_id', $adminId)
            ->where('jajman_id', $jajmanId)
            ->update([
                'has_possible_duplicates' => $hasDuplicates,
                'duplicate_candidates' => $hasDuplicates ? json_encode(array_values(array_unique($candidateIds))) : null,
                'updated_at' => now(),
            ]);
    }

    /**
     * Add a candidate ID to an existing Jajman's duplicate list in the pivot.
     */
    protected function addDuplicateCandidateToJajman(int $adminId, int $targetJajmanId, int $newCandidateId): void
    {
        $pivot = DB::table('professional_jajmans')
            ->where('admin_id', $adminId)
            ->where('jajman_id', $targetJajmanId)
            ->first();

        if (!$pivot) {
            return;
        }

        $existing = [];
        if (!empty($pivot->duplicate_candidates)) {
            $decoded = json_decode((string) $pivot->duplicate_candidates, true);
            if (is_array($decoded)) {
                $existing = $decoded;
            }
        }

        if (!in_array($newCandidateId, $existing, true)) {
            $existing[] = $newCandidateId;
        }

        DB::table('professional_jajmans')
            ->where('admin_id', $adminId)
            ->where('jajman_id', $targetJajmanId)
            ->update([
                'has_possible_duplicates' => true,
                'duplicate_candidates' => json_encode(array_values(array_unique($existing))),
                'updated_at' => now(),
            ]);
    }

    /**
     * Copy primary birth detail from UserAstrologyProfile to JajmanBirthDetail if missing on Jajman.
     */
    protected function syncUserBirthDetailIfMissing(Jajman $jajman, User $user): void
    {
        if ($jajman->primaryBirthDetail()->exists()) {
            return;
        }

        $userProfile = UserAstrologyProfile::where('user_id', $user->id)
            ->where('is_default', true)
            ->first() ?? UserAstrologyProfile::where('user_id', $user->id)->first();

        if (!$userProfile) {
            return;
        }

        try {
            $birthPlace = trim("{$userProfile->birth_city}, {$userProfile->birth_state}, {$userProfile->birth_country}", " ,");
            if (empty($birthPlace)) {
                $birthPlace = $user->place_of_birth ?? 'Unknown';
            }

            JajmanBirthDetail::create([
                'jajman_id' => $jajman->id,
                'date_of_birth' => $userProfile->date_of_birth,
                'birth_time' => $userProfile->birth_time,
                'time_unknown' => $userProfile->birth_time_precision === UserAstrologyProfile::PRECISION_UNKNOWN,
                'birth_place' => $birthPlace,
                'latitude' => $userProfile->latitude,
                'longitude' => $userProfile->longitude,
                'timezone' => $userProfile->timezone ?? 'Asia/Kolkata',
                'is_primary' => true,
            ]);

            // Also backfill date_of_birth on Jajman if empty
            if (empty($jajman->date_of_birth) && !empty($userProfile->date_of_birth)) {
                $jajman->date_of_birth = $userProfile->date_of_birth;
                $jajman->save();
            }
        } catch (\Throwable $e) {
            Log::warning('Could not sync user astrology profile to Jajman birth detail: ' . $e->getMessage());
        }
    }

    /**
     * Ensure a Jajman record exists and is linked for a practitioner given manual/external person details.
     * Follows Task B3 deduplication:
     * - Exactly 1 match in practitioner's CRM -> associate/update & return.
     * - 2+ matches in practitioner's CRM -> flag possible duplicates without blind merge, create new record.
     * - 0 matches -> check unlinked/soft-deleted records or create new manual Jajman.
     */
    public function ensureJajmanForPerson(int $adminId, array $personData): Jajman
    {
        return DB::transaction(function () use ($adminId, $personData) {
            $phoneNorm = ContactNormalization::normalizePhone($personData['phone'] ?? null);
            $emailNorm = ContactNormalization::normalizeEmail($personData['email'] ?? null);

            // Find matching candidate Jajmans currently active in practitioner's CRM
            $candidates = $this->findPotentialDuplicateJajmans(
                $adminId,
                0,
                $phoneNorm,
                $emailNorm
            );

            // Case A: Exactly 1 match found in practitioner's practice
            if ($candidates->count() === 1) {
                /** @var Jajman $matchedJajman */
                $matchedJajman = $candidates->first();

                // Backfill details if missing
                if (empty($matchedJajman->date_of_birth) && !empty($personData['date_of_birth'])) {
                    $matchedJajman->date_of_birth = $personData['date_of_birth'];
                }
                if (empty($matchedJajman->city) && !empty($personData['birth_city'])) {
                    $matchedJajman->city = $personData['birth_city'];
                }
                if (empty($matchedJajman->gender) && !empty($personData['gender'])) {
                    $matchedJajman->gender = $personData['gender'];
                }
                $matchedJajman->save();

                // Ensure primary birth detail exists
                $this->ensureBirthDetailForJajman($matchedJajman, $personData);

                $matchedJajman->setAttribute('has_possible_duplicates', false);
                $matchedJajman->setAttribute('possible_duplicate_ids', []);

                return $matchedJajman;
            }

            // Case B: Ambiguous match (2 or more candidates for this practitioner)
            // Do NOT blindly merge; create a new Jajman and flag duplicates on all records
            if ($candidates->count() >= 2) {
                $candidateIds = $candidates->pluck('id')->values()->all();

                $newJajman = Jajman::create([
                    'name' => $personData['name'],
                    'phone' => $personData['phone'] ?? null,
                    'email' => $personData['email'] ?? null,
                    'gender' => $personData['gender'] ?? null,
                    'date_of_birth' => $personData['date_of_birth'] ?? null,
                    'city' => $personData['birth_city'] ?? null,
                    'user_id' => null,
                ]);

                DB::table('professional_jajmans')->insert([
                    'admin_id' => $adminId,
                    'jajman_id' => $newJajman->id,
                    'status' => 'active',
                    'source' => 'manual',
                    'has_possible_duplicates' => true,
                    'duplicate_candidates' => json_encode($candidateIds),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                foreach ($candidates as $c) {
                    $this->addDuplicateCandidateToJajman($adminId, $c->id, $newJajman->id);
                }

                $this->ensureBirthDetailForJajman($newJajman, $personData);

                $newJajman->setAttribute('has_possible_duplicates', true);
                $newJajman->setAttribute('possible_duplicate_ids', $candidateIds);

                return $newJajman;
            }

            // Case C: 0 matches in this practitioner's practice
            // Check if there is an unlinked or soft-deleted Jajman matching this phone/email
            $existingGlobal = null;
            if ($phoneNorm !== null || $emailNorm !== null) {
                $existingGlobal = Jajman::withTrashed()
                    ->when($phoneNorm, fn($q) => $q->where('phone', $phoneNorm)->orWhere('phone', $personData['phone'] ?? ''))
                    ->when($emailNorm, fn($q) => $q->orWhere('email', $emailNorm))
                    ->first();
            }

            if ($existingGlobal) {
                if ($existingGlobal->trashed()) {
                    $existingGlobal->restore();
                }
                $jajmanToLink = $existingGlobal;
                if (empty($jajmanToLink->date_of_birth) && !empty($personData['date_of_birth'])) {
                    $jajmanToLink->date_of_birth = $personData['date_of_birth'];
                    $jajmanToLink->save();
                }
            } else {
                $jajmanToLink = Jajman::create([
                    'name' => $personData['name'],
                    'phone' => $personData['phone'] ?? null,
                    'email' => $personData['email'] ?? null,
                    'gender' => $personData['gender'] ?? null,
                    'date_of_birth' => $personData['date_of_birth'] ?? null,
                    'city' => $personData['birth_city'] ?? null,
                    'user_id' => null,
                ]);
            }

            // Link to practitioner if not already linked in pivot
            $pivot = DB::table('professional_jajmans')
                ->where('admin_id', $adminId)
                ->where('jajman_id', $jajmanToLink->id)
                ->first();

            if (!$pivot) {
                DB::table('professional_jajmans')->insert([
                    'admin_id' => $adminId,
                    'jajman_id' => $jajmanToLink->id,
                    'status' => 'active',
                    'source' => 'manual',
                    'has_possible_duplicates' => false,
                    'duplicate_candidates' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $this->ensureBirthDetailForJajman($jajmanToLink, $personData);

            $jajmanToLink->setAttribute('has_possible_duplicates', false);
            $jajmanToLink->setAttribute('possible_duplicate_ids', []);

            return $jajmanToLink;
        });
    }

    /**
     * Ensure primary birth detail exists for a Jajman when given person birth data.
     */
    protected function ensureBirthDetailForJajman(Jajman $jajman, array $personData): void
    {
        if ($jajman->primaryBirthDetail()->exists()) {
            return;
        }

        if (empty($personData['date_of_birth']) || !isset($personData['latitude']) || !isset($personData['longitude'])) {
            return;
        }

        try {
            JajmanBirthDetail::create([
                'jajman_id' => $jajman->id,
                'date_of_birth' => $personData['date_of_birth'],
                'birth_time' => $personData['birth_time'] ?? null,
                'time_unknown' => !empty($personData['time_unknown']) || (($personData['birth_time_precision'] ?? '') === 'unknown'),
                'birth_place' => $personData['birth_city'] ?? $personData['city'] ?? 'Unknown',
                'latitude' => $personData['latitude'],
                'longitude' => $personData['longitude'],
                'timezone' => $personData['timezone'] ?? 'Asia/Kolkata',
                'is_primary' => true,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Could not create primary birth detail for Jajman: ' . $e->getMessage());
        }
    }
}
