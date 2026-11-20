<?php

namespace App\Services\Jajman;

use App\Models\Appointment;
use App\Models\Consultation;
use App\Models\Jajman;
use App\Models\JajmanBirthDetail;
use App\Models\JajmanFamilyRelationship;
use App\Models\JajmanMergeLog;
use App\Models\JajmanNote;
use App\Models\ProfessionalBookingRequest;
use App\Models\ProfessionalInvoice;
use App\Models\ProfessionalTransaction;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class JajmanMergeService
{
    /**
     * Retrieve side-by-side comparison data for two Jajmans under an astrologer's management.
     *
     * @param int $adminId
     * @param int $jajmanAId
     * @param int $jajmanBId
     * @return array
     * @throws InvalidArgumentException
     * @throws AuthorizationException
     */
    public function getComparison(int $adminId, int $jajmanAId, int $jajmanBId): array
    {
        if ($jajmanAId === $jajmanBId) {
            throw new InvalidArgumentException('Cannot compare a Jajman record with itself.');
        }

        $jajmanA = $this->loadManagedJajman($adminId, $jajmanAId);
        $jajmanB = $this->loadManagedJajman($adminId, $jajmanBId);

        return [
            'jajman_a' => $this->formatComparisonPayload($adminId, $jajmanA),
            'jajman_b' => $this->formatComparisonPayload($adminId, $jajmanB),
        ];
    }

    /**
     * Merge secondary Jajman into primary Jajman within a safe, auditable transaction.
     *
     * @param int $adminId
     * @param int $primaryId
     * @param int $secondaryId
     * @return array
     * @throws InvalidArgumentException
     * @throws AuthorizationException
     */
    public function merge(int $adminId, int $primaryId, int $secondaryId): array
    {
        if ($primaryId === $secondaryId) {
            throw new InvalidArgumentException('Primary and secondary Jajman cannot be the same record.');
        }

        return DB::transaction(function () use ($adminId, $primaryId, $secondaryId) {
            // 1. Lock and verify both records belong to the professional and are not already merged
            $primary = Jajman::lockForUpdate()->find($primaryId);
            $secondary = Jajman::lockForUpdate()->find($secondaryId);

            if (!$primary || !$secondary) {
                throw new InvalidArgumentException('One or both Jajman records could not be found.');
            }

            if ($primary->merged_into_jajman_id || $secondary->merged_into_jajman_id) {
                throw new InvalidArgumentException('One of the specified Jajman records has already been merged.');
            }

            // Verify both are linked to the practitioner
            $primaryLinked = DB::table('professional_jajmans')
                ->where('admin_id', $adminId)
                ->where('jajman_id', $primaryId)
                ->exists();

            $secondaryLinked = DB::table('professional_jajmans')
                ->where('admin_id', $adminId)
                ->where('jajman_id', $secondaryId)
                ->exists();

            if (!$primaryLinked || !$secondaryLinked) {
                throw new AuthorizationException('You are not authorized to merge Jajman records outside your CRM roster.');
            }

            // 2. Capture snapshots for audit log
            $preMergePrimary = $primary->toArray();
            $preMergeSecondary = $secondary->toArray();

            $consolidatedCounts = [
                'birth_details_moved' => 0,
                'notes_moved' => 0,
                'family_relationships_moved' => 0,
                'consultations_moved' => 0,
                'appointments_moved' => 0,
                'transactions_moved' => 0,
                'invoices_moved' => 0,
                'booking_requests_moved' => 0,
            ];

            // 3. Backfill missing/blank fields on primary from secondary
            $backfillFields = ['phone', 'email', 'gender', 'date_of_birth', 'profile_photo', 'city', 'user_id'];
            $updatedPrimary = false;

            foreach ($backfillFields as $field) {
                $primaryVal = $primary->$field;
                $secondaryVal = $secondary->$field;

                $isPrimaryEmpty = is_null($primaryVal) || (is_string($primaryVal) && trim($primaryVal) === '');
                $isSecondaryFilled = !is_null($secondaryVal) && (!is_string($secondaryVal) || trim($secondaryVal) !== '');

                if ($isPrimaryEmpty && $isSecondaryFilled) {
                    $primary->$field = $secondaryVal;
                    $updatedPrimary = true;
                }
            }

            if ($updatedPrimary) {
                $primary->save();
            }

            // 4. Consolidate birth details
            $hasPrimaryBirthDetail = JajmanBirthDetail::where('jajman_id', $primaryId)
                ->where('is_primary', true)
                ->exists();

            $secondaryBirthDetails = JajmanBirthDetail::where('jajman_id', $secondaryId)->get();
            foreach ($secondaryBirthDetails as $bd) {
                $bd->jajman_id = $primaryId;
                if ($hasPrimaryBirthDetail) {
                    $bd->is_primary = false;
                } elseif ($bd->is_primary) {
                    $hasPrimaryBirthDetail = true;
                }
                $bd->save();
                $consolidatedCounts['birth_details_moved']++;
            }

            // 5. Consolidate notes
            $notesCount = JajmanNote::where('jajman_id', $secondaryId)->update(['jajman_id' => $primaryId]);
            $consolidatedCounts['notes_moved'] = $notesCount;

            // 6. Consolidate family relationships (scoped by admin_id)
            // Case A: Jajman is the source (jajman_id = secondaryId)
            $sourceRelationships = JajmanFamilyRelationship::where('jajman_id', $secondaryId)->get();
            foreach ($sourceRelationships as $rel) {
                if ($rel->related_jajman_id == $primaryId) {
                    // Avoid self-referencing relationship
                    $rel->delete();
                    continue;
                }

                $alreadyExists = JajmanFamilyRelationship::where('admin_id', $rel->admin_id)
                    ->where('jajman_id', $primaryId)
                    ->where('related_jajman_id', $rel->related_jajman_id)
                    ->exists();

                if ($alreadyExists) {
                    $rel->delete();
                } else {
                    $rel->jajman_id = $primaryId;
                    $rel->save();
                    $consolidatedCounts['family_relationships_moved']++;
                }
            }

            // Case B: Jajman is the target (related_jajman_id = secondaryId)
            $targetRelationships = JajmanFamilyRelationship::where('related_jajman_id', $secondaryId)->get();
            foreach ($targetRelationships as $rel) {
                if ($rel->jajman_id == $primaryId) {
                    // Avoid self-referencing relationship
                    $rel->delete();
                    continue;
                }

                $alreadyExists = JajmanFamilyRelationship::where('admin_id', $rel->admin_id)
                    ->where('jajman_id', $rel->jajman_id)
                    ->where('related_jajman_id', $primaryId)
                    ->exists();

                if ($alreadyExists) {
                    $rel->delete();
                } else {
                    $rel->related_jajman_id = $primaryId;
                    $rel->save();
                    $consolidatedCounts['family_relationships_moved']++;
                }
            }

            // 7. Consolidate consultations
            $consultationsCount = Consultation::where('jajman_id', $secondaryId)->update(['jajman_id' => $primaryId]);
            $consolidatedCounts['consultations_moved'] = $consultationsCount;

            // 8. Consolidate appointments
            $appointmentsCount = Appointment::where('jajman_id', $secondaryId)->update(['jajman_id' => $primaryId]);
            $consolidatedCounts['appointments_moved'] = $appointmentsCount;

            // 9. Consolidate transactions
            $transactionsCount = ProfessionalTransaction::where('jajman_id', $secondaryId)->update(['jajman_id' => $primaryId]);
            $consolidatedCounts['transactions_moved'] = $transactionsCount;

            // 10. Consolidate invoices
            $invoicesCount = ProfessionalInvoice::where('jajman_id', $secondaryId)->update(['jajman_id' => $primaryId]);
            $consolidatedCounts['invoices_moved'] = $invoicesCount;

            // 11. Consolidate booking requests
            if (class_exists(ProfessionalBookingRequest::class)) {
                $bookingRequestsCount = ProfessionalBookingRequest::where('jajman_id', $secondaryId)->update(['jajman_id' => $primaryId]);
                $consolidatedCounts['booking_requests_moved'] = $bookingRequestsCount;
            }

            // 11b. Consolidate stored Kundli reports
            if (Schema::hasTable('jajman_kundli_reports')) {
                $kundliReportsCount = DB::table('jajman_kundli_reports')
                    ->where('jajman_id', $secondaryId)
                    ->update(['jajman_id' => $primaryId]);
                $consolidatedCounts['kundli_reports_moved'] = $kundliReportsCount;
            }

            // 12. Update professional_jajmans pivot records
            // Mark the secondary pivot as merged for this professional so it doesn't appear in active roster
            DB::table('professional_jajmans')
                ->where('admin_id', $adminId)
                ->where('jajman_id', $secondaryId)
                ->update(['status' => 'merged']);

            // 13. Mark secondary Jajman as merged and soft-delete
            $secondary->merged_into_jajman_id = $primaryId;
            $secondary->merged_at = now();
            $secondary->save();
            $secondary->delete();

            // 14. Clean up duplicate candidates references
            $this->cleanDuplicateCandidates($adminId, $primaryId, $secondaryId);

            // 15. Record audit log
            $mergeLog = JajmanMergeLog::create([
                'admin_id' => $adminId,
                'primary_jajman_id' => $primaryId,
                'secondary_jajman_id' => $secondaryId,
                'pre_merge_primary_snapshot' => $preMergePrimary,
                'pre_merge_secondary_snapshot' => $preMergeSecondary,
                'consolidated_counts' => $consolidatedCounts,
            ]);

            return [
                'primary_jajman' => $primary->fresh(['primaryBirthDetail']),
                'secondary_jajman_id' => $secondaryId,
                'consolidated_counts' => $consolidatedCounts,
                'merge_log_id' => $mergeLog->id,
            ];
        });
    }

    /**
     * Remove the secondary ID from duplicate candidates across this astrologer's roster.
     */
    protected function cleanDuplicateCandidates(int $adminId, int $primaryId, int $secondaryId): void
    {
        $pivots = DB::table('professional_jajmans')
            ->where('admin_id', $adminId)
            ->whereNotNull('duplicate_candidates')
            ->get();

        foreach ($pivots as $pivot) {
            $raw = $pivot->duplicate_candidates;
            $candidates = is_string($raw) ? json_decode($raw, true) : (array) $raw;
            if (!is_array($candidates)) {
                continue;
            }

            // Remove secondary ID, and also if this is the primary record, remove secondary ID
            $filtered = array_values(array_filter($candidates, function ($id) use ($secondaryId, $pivot, $primaryId) {
                if ((int)$id === $secondaryId) {
                    return false;
                }
                // If this is primary record, ensure it doesn't have itself as candidate
                if ($pivot->jajman_id == $primaryId && (int)$id === $primaryId) {
                    return false;
                }
                return true;
            }));

            $hasDuplicates = !empty($filtered);

            DB::table('professional_jajmans')
                ->where('id', $pivot->id)
                ->update([
                    'has_possible_duplicates' => $hasDuplicates,
                    'duplicate_candidates' => $hasDuplicates ? json_encode($filtered) : null,
                ]);
        }
    }

    /**
     * Load a Jajman record ensuring it is managed by the professional.
     */
    protected function loadManagedJajman(int $adminId, int $jajmanId): Jajman
    {
        $jajman = Jajman::with(['primaryBirthDetail', 'birthDetails'])
            ->whereHas('professionals', function ($query) use ($adminId) {
                $query->where('admins.id', $adminId);
            })
            ->find($jajmanId);

        if (!$jajman) {
            throw new AuthorizationException("Jajman #{$jajmanId} was not found or is not managed by your account.");
        }

        return $jajman;
    }

    /**
     * Format a comparison payload including child counts.
     */
    protected function formatComparisonPayload(int $adminId, Jajman $jajman): array
    {
        return [
            'id' => $jajman->id,
            'name' => $jajman->name,
            'phone' => $jajman->phone,
            'email' => $jajman->email,
            'gender' => $jajman->gender,
            'date_of_birth' => $jajman->date_of_birth ? $jajman->date_of_birth->format('Y-m-d') : null,
            'city' => $jajman->city,
            'profile_photo' => $jajman->profile_photo,
            'avatar_url' => $jajman->avatar_url,
            'user_id' => $jajman->user_id,
            'primary_birth_detail' => $jajman->primaryBirthDetail ? [
                'date_of_birth' => $jajman->primaryBirthDetail->date_of_birth,
                'birth_time' => $jajman->primaryBirthDetail->birth_time,
                'time_unknown' => (bool)$jajman->primaryBirthDetail->time_unknown,
                'birth_place' => $jajman->primaryBirthDetail->birth_place,
                'latitude' => $jajman->primaryBirthDetail->latitude,
                'longitude' => $jajman->primaryBirthDetail->longitude,
                'timezone' => $jajman->primaryBirthDetail->timezone,
            ] : null,
            'counts' => [
                'birth_details' => JajmanBirthDetail::where('jajman_id', $jajman->id)->count(),
                'notes' => JajmanNote::where('admin_id', $adminId)->where('jajman_id', $jajman->id)->count(),
                'family_relationships' => JajmanFamilyRelationship::where('admin_id', $adminId)
                    ->where(function ($q) use ($jajman) {
                        $q->where('jajman_id', $jajman->id)->orWhere('related_jajman_id', $jajman->id);
                    })->count(),
                'consultations' => Consultation::where('jajman_id', $jajman->id)->count(),
                'appointments' => Appointment::where('jajman_id', $jajman->id)->count(),
                'transactions' => ProfessionalTransaction::where('jajman_id', $jajman->id)->count(),
                'invoices' => ProfessionalInvoice::where('jajman_id', $jajman->id)->count(),
                'booking_requests' => class_exists(ProfessionalBookingRequest::class)
                    ? ProfessionalBookingRequest::where('jajman_id', $jajman->id)->count()
                    : 0,
            ],
        ];
    }
}
