<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Jajman;
use App\Models\JajmanBirthDetail;
use App\Models\JajmanFamilyRelationship;
use App\Models\JajmanNote;
use App\Services\Jajman\JajmanMergeService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class JajmanApiController extends Controller
{
    /**
     * Helper to verify that the Jajman belongs to the authenticated professional.
     */
    protected function authorizeJajman(Request $request, $jajmanId): Jajman
    {
        $admin = $request->user();

        $jajman = Jajman::whereHas('professionals', function ($q) use ($admin) {
            $q->where('admins.id', $admin->id);
        })->find($jajmanId);

        if (! $jajman) {
            abort(404, 'Jajman record not found in your professional practice.');
        }

        return $jajman;
    }

    /**
     * Enforce personal data sharing consent for linked Astro Setu consumer accounts.
     * Preserves manual offline Jajman records created directly by the astrologer.
     */
    protected function filterJajmanConsent(Jajman $jajman, int $adminId): Jajman
    {
        $pivot = $jajman->relationLoaded('professionals')
            ? $jajman->professionals->firstWhere('id', $adminId)?->pivot
            : null;

        if (!$pivot) {
            $pivot = DB::table('professional_jajmans')
                ->where('admin_id', $adminId)
                ->where('jajman_id', $jajman->id)
                ->first();
        }

        if ($pivot) {
            $jajman->setAttribute('has_possible_duplicates', (bool) ($pivot->has_possible_duplicates ?? false));
            $candidates = [];
            if (!empty($pivot->duplicate_candidates)) {
                $decoded = is_array($pivot->duplicate_candidates)
                    ? $pivot->duplicate_candidates
                    : json_decode((string) $pivot->duplicate_candidates, true);
                if (is_array($decoded)) {
                    $candidates = array_map('intval', $decoded);
                }
            }
            $jajman->setAttribute('possible_duplicate_ids', $candidates);
        } else {
            $jajman->setAttribute('has_possible_duplicates', (bool) ($jajman->has_possible_duplicates ?? false));
            $jajman->setAttribute('possible_duplicate_ids', $jajman->possible_duplicate_ids ?? []);
        }

        if ($jajman->user_id === null) {
            // Manual offline entry created by practitioner
            $jajman->setAttribute('is_consent_granted', true);
            $jajman->setAttribute('consent_status', 'manual');
            return $jajman;
        }

        $consentGranted = \App\Models\CustomerAstrologerConsent::where('user_id', $jajman->user_id)
            ->where('admin_id', $adminId)
            ->where('status', \App\Models\CustomerAstrologerConsent::STATUS_GRANTED)
            ->exists();

        if (!$consentGranted) {
            $jajman->phone = null;
            $jajman->email = null;
            $jajman->date_of_birth = null;
            if ($jajman->relationLoaded('birthDetails')) {
                $jajman->setRelation('birthDetails', collect([]));
            }
            if ($jajman->relationLoaded('primaryBirthDetail')) {
                $jajman->setRelation('primaryBirthDetail', null);
            }
            $jajman->setAttribute('is_consent_granted', false);
            $jajman->setAttribute('consent_status', 'withheld');
        } else {
            $jajman->setAttribute('is_consent_granted', true);
            $jajman->setAttribute('consent_status', 'granted');
        }

        return $jajman;
    }

    /**
     * 1. GET /api/astrologer/jajmans
     * List Jajmans for the authenticated professional with search, sort & pagination.
     */
    public function index(Request $request)
    {
        $admin = $request->user();

        $query = Jajman::whereHas('professionals', function ($q) use ($admin) {
            $q->where('admins.id', $admin->id);
        })->with('primaryBirthDetail');

        // Search by name or phone
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'created_at'); // created_at, name
        $sortOrder = strtolower($request->input('sort_order', 'desc')) === 'asc' ? 'asc' : 'desc';

        if (in_array($sortBy, ['name', 'created_at', 'updated_at'])) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->latest();
        }

        $perPage = min(max((int) $request->input('per_page', 15), 1), 100);
        $jajmans = $query->paginate($perPage);

        $filteredItems = collect($jajmans->items())->map(function ($j) use ($admin) {
            return $this->filterJajmanConsent($j, $admin->id);
        })->values();

        return response()->json([
            'status' => 'success',
            'data' => $filteredItems,
            'meta' => [
                'current_page' => $jajmans->currentPage(),
                'last_page' => $jajmans->lastPage(),
                'per_page' => $jajmans->perPage(),
                'total' => $jajmans->total(),
            ],
        ]);
    }

    /**
     * 2. POST /api/astrologer/jajmans
     * Create a new Jajman and link to authenticated professional.
     */
    public function store(Request $request)
    {
        $admin = $request->user();

        $validated = $request->validate([
            'name' => 'required|string|max:191',
            'phone' => 'nullable|string|max:25',
            'email' => 'nullable|email|max:191',
            'gender' => ['nullable', Rule::in(['male', 'female', 'other'])],
            'date_of_birth' => 'nullable|date',
            'city' => 'nullable|string|max:191',
            'birth_time' => 'nullable|string',
            'birth_place' => 'nullable|string|max:191',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'timezone' => 'nullable|string|max:50',
            'time_unknown' => 'nullable|boolean',
        ]);

        return DB::transaction(function () use ($admin, $validated) {
            $jajman = Jajman::create([
                'name' => $validated['name'],
                'phone' => $validated['phone'] ?? null,
                'email' => $validated['email'] ?? null,
                'gender' => $validated['gender'] ?? null,
                'date_of_birth' => $validated['date_of_birth'] ?? null,
                'city' => $validated['city'] ?? null,
            ]);

            // Link to the authenticated professional
            $jajman->professionals()->attach($admin->id, [
                'status' => 'active',
                'source' => 'manual',
            ]);

            // Optional: Create initial primary birth detail if provided
            if (!empty($validated['date_of_birth']) || !empty($validated['birth_place'])) {
                JajmanBirthDetail::create([
                    'jajman_id' => $jajman->id,
                    'date_of_birth' => $validated['date_of_birth'] ?? now()->toDateString(),
                    'birth_time' => $validated['birth_time'] ?? null,
                    'time_unknown' => $validated['time_unknown'] ?? false,
                    'birth_place' => $validated['birth_place'] ?? ($validated['city'] ?? 'Unknown'),
                    'latitude' => $validated['latitude'] ?? null,
                    'longitude' => $validated['longitude'] ?? null,
                    'timezone' => $validated['timezone'] ?? null,
                    'is_primary' => true,
                ]);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Jajman created successfully.',
                'data' => $jajman->load('primaryBirthDetail'),
            ], 201);
        });
    }

    /**
     * 3. GET /api/astrologer/jajmans/{id}
     * Retrieve full Jajman profile, birth details, family, and private notes.
     */
    public function show(Request $request, $id)
    {
        $admin = $request->user();
        $jajman = $this->authorizeJajman($request, $id);

        $jajman->load(['birthDetails', 'primaryBirthDetail']);
        $jajman = $this->filterJajmanConsent($jajman, $admin->id);
        $notes = $jajman->notesForProfessional($admin->id)->get();
        $family = $jajman->familyForProfessional($admin->id)->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'jajman' => $jajman,
                'notes' => $notes,
                'family' => $family,
            ],
        ]);
    }

    /**
     * 4. PUT /api/astrologer/jajmans/{id}
     * Update Jajman basic identity information.
     */
    public function update(Request $request, $id)
    {
        $jajman = $this->authorizeJajman($request, $id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:191',
            'phone' => 'nullable|string|max:25',
            'email' => 'nullable|email|max:191',
            'gender' => ['nullable', Rule::in(['male', 'female', 'other'])],
            'date_of_birth' => 'nullable|date',
            'city' => 'nullable|string|max:191',
        ]);

        $jajman->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Jajman details updated successfully.',
            'data' => $jajman->fresh('primaryBirthDetail'),
        ]);
    }

    /**
     * 5. DELETE /api/astrologer/jajmans/{id}
     * Unlink Jajman from current professional (and soft delete if orphan).
     */
    public function destroy(Request $request, $id)
    {
        $admin = $request->user();
        $jajman = $this->authorizeJajman($request, $id);

        DB::transaction(function () use ($admin, $jajman) {
            // Delete private notes and family relationships defined by this professional
            JajmanNote::where('admin_id', $admin->id)->where('jajman_id', $jajman->id)->delete();
            JajmanFamilyRelationship::where('admin_id', $admin->id)
                ->where(function ($q) use ($jajman) {
                    $q->where('jajman_id', $jajman->id)->orWhere('related_jajman_id', $jajman->id);
                })->delete();

            // Detach professional link
            $jajman->professionals()->detach($admin->id);

            // If no other professionals manage this record, soft delete it
            if ($jajman->professionals()->count() === 0) {
                $jajman->delete();
            }
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Jajman removed from your workspace.',
        ]);
    }

    // ==========================================
    // BIRTH DETAILS ENDPOINTS
    // ==========================================

    public function getBirthDetails(Request $request, $jajmanId)
    {
        $jajman = $this->authorizeJajman($request, $jajmanId);

        return response()->json([
            'status' => 'success',
            'data' => $jajman->birthDetails()->latest()->get(),
        ]);
    }

    public function storeBirthDetail(Request $request, $jajmanId)
    {
        $jajman = $this->authorizeJajman($request, $jajmanId);

        $validated = $request->validate([
            'date_of_birth' => 'required|date',
            'birth_time' => 'nullable|string',
            'time_unknown' => 'nullable|boolean',
            'birth_place' => 'required|string|max:191',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'timezone' => 'nullable|string|max:50',
            'is_primary' => 'nullable|boolean',
        ]);

        return DB::transaction(function () use ($jajman, $validated) {
            $isPrimary = $validated['is_primary'] ?? true;

            if ($isPrimary) {
                $jajman->birthDetails()->update(['is_primary' => false]);
            }

            $birthDetail = $jajman->birthDetails()->create([
                'date_of_birth' => $validated['date_of_birth'],
                'birth_time' => $validated['birth_time'] ?? null,
                'time_unknown' => $validated['time_unknown'] ?? false,
                'birth_place' => $validated['birth_place'],
                'latitude' => $validated['latitude'] ?? null,
                'longitude' => $validated['longitude'] ?? null,
                'timezone' => $validated['timezone'] ?? null,
                'is_primary' => $isPrimary,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Birth details saved successfully.',
                'data' => $birthDetail,
            ], 201);
        });
    }

    public function updateBirthDetail(Request $request, $jajmanId, $birthDetailId)
    {
        $jajman = $this->authorizeJajman($request, $jajmanId);
        $birthDetail = $jajman->birthDetails()->findOrFail($birthDetailId);

        $validated = $request->validate([
            'date_of_birth' => 'sometimes|required|date',
            'birth_time' => 'nullable|string',
            'time_unknown' => 'nullable|boolean',
            'birth_place' => 'sometimes|required|string|max:191',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'timezone' => 'nullable|string|max:50',
            'is_primary' => 'nullable|boolean',
        ]);

        return DB::transaction(function () use ($jajman, $birthDetail, $validated) {
            if (!empty($validated['is_primary']) && $validated['is_primary']) {
                $jajman->birthDetails()->where('id', '!=', $birthDetail->id)->update(['is_primary' => false]);
            }

            $birthDetail->update($validated);

            return response()->json([
                'status' => 'success',
                'message' => 'Birth details updated successfully.',
                'data' => $birthDetail,
            ]);
        });
    }

    public function destroyBirthDetail(Request $request, $jajmanId, $birthDetailId)
    {
        $jajman = $this->authorizeJajman($request, $jajmanId);
        $birthDetail = $jajman->birthDetails()->findOrFail($birthDetailId);

        $birthDetail->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Birth detail deleted.',
        ]);
    }

    // ==========================================
    // FAMILY RELATIONSHIPS ENDPOINTS
    // ==========================================

    public function getFamily(Request $request, $jajmanId)
    {
        $admin = $request->user();
        $jajman = $this->authorizeJajman($request, $jajmanId);

        $family = $jajman->familyForProfessional($admin->id)->get();

        return response()->json([
            'status' => 'success',
            'data' => $family,
        ]);
    }

    public function storeFamily(Request $request, $jajmanId)
    {
        $admin = $request->user();
        $jajman = $this->authorizeJajman($request, $jajmanId);

        $validated = $request->validate([
            'relationship_type' => ['required', Rule::in([
                'father', 'mother', 'spouse', 'son', 'daughter', 'brother', 'sister', 'other'
            ])],
            'related_jajman_id' => 'nullable|exists:jajmans,id',
            // Or create new Jajman inline
            'name' => 'required_without:related_jajman_id|string|max:191',
            'phone' => 'nullable|string|max:25',
            'date_of_birth' => 'nullable|date',
            'birth_place' => 'nullable|string|max:191',
        ]);

        return DB::transaction(function () use ($admin, $jajman, $validated) {
            $relatedId = $validated['related_jajman_id'] ?? null;

            if ($relatedId) {
                // Ensure related jajman is also owned by this professional
                $this->authorizeJajman(request(), $relatedId);
            } else {
                // Create new jajman
                $newJajman = Jajman::create([
                    'name' => $validated['name'],
                    'phone' => $validated['phone'] ?? null,
                    'date_of_birth' => $validated['date_of_birth'] ?? null,
                    'city' => $validated['birth_place'] ?? null,
                ]);
                $newJajman->professionals()->attach($admin->id, ['status' => 'active', 'source' => 'family_inline']);
                $relatedId = $newJajman->id;
            }

            if ($relatedId == $jajman->id) {
                abort(422, 'Cannot add a person as their own family member.');
            }

            $relation = JajmanFamilyRelationship::updateOrCreate([
                'admin_id' => $admin->id,
                'jajman_id' => $jajman->id,
                'related_jajman_id' => $relatedId,
            ], [
                'relationship_type' => $validated['relationship_type'],
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Family relationship recorded.',
                'data' => $relation->load('relatedJajman.primaryBirthDetail'),
            ], 201);
        });
    }

    public function destroyFamily(Request $request, $jajmanId, $relationshipId)
    {
        $admin = $request->user();
        $jajman = $this->authorizeJajman($request, $jajmanId);

        $relation = JajmanFamilyRelationship::where('admin_id', $admin->id)
            ->where('jajman_id', $jajman->id)
            ->findOrFail($relationshipId);

        $relation->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Family relationship removed.',
        ]);
    }

    // ==========================================
    // PRIVATE NOTES ENDPOINTS
    // ==========================================

    public function getNotes(Request $request, $jajmanId)
    {
        $admin = $request->user();
        $jajman = $this->authorizeJajman($request, $jajmanId);

        return response()->json([
            'status' => 'success',
            'data' => $jajman->notesForProfessional($admin->id)->get(),
        ]);
    }

    public function storeNote(Request $request, $jajmanId)
    {
        $admin = $request->user();
        $jajman = $this->authorizeJajman($request, $jajmanId);

        $validated = $request->validate([
            'content' => 'required|string',
        ]);

        $note = JajmanNote::create([
            'admin_id' => $admin->id,
            'jajman_id' => $jajman->id,
            'content' => $validated['content'],
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Note created successfully.',
            'data' => $note,
        ], 201);
    }

    public function updateNote(Request $request, $jajmanId, $noteId)
    {
        $admin = $request->user();
        $jajman = $this->authorizeJajman($request, $jajmanId);

        $note = JajmanNote::where('admin_id', $admin->id)
            ->where('jajman_id', $jajman->id)
            ->findOrFail($noteId);

        $validated = $request->validate([
            'content' => 'required|string',
        ]);

        $note->update(['content' => $validated['content']]);

        return response()->json([
            'status' => 'success',
            'message' => 'Note updated successfully.',
            'data' => $note,
        ]);
    }

    public function destroyNote(Request $request, $jajmanId, $noteId)
    {
        $admin = $request->user();
        $jajman = $this->authorizeJajman($request, $jajmanId);

        $note = JajmanNote::where('admin_id', $admin->id)
            ->where('jajman_id', $jajman->id)
            ->findOrFail($noteId);

        $note->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Note deleted successfully.',
        ]);
    }

    /**
     * Retrieve side-by-side comparison of two Jajman records for duplicate merging.
     */
    public function compareForMerge(Request $request, JajmanMergeService $mergeService)
    {
        $validated = $request->validate([
            'jajman_a' => 'required|integer',
            'jajman_b' => 'required|integer',
        ]);

        $admin = $request->user();

        try {
            $comparison = $mergeService->getComparison($admin->id, (int)$validated['jajman_a'], (int)$validated['jajman_b']);

            return response()->json([
                'status' => 'success',
                'data' => $comparison,
            ]);
        } catch (AuthorizationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 403);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Execute manual duplicate merge consolidating all history into the primary record.
     */
    public function merge(Request $request, JajmanMergeService $mergeService)
    {
        $validated = $request->validate([
            'primary_jajman_id' => 'required|integer',
            'secondary_jajman_id' => 'required|integer',
        ]);

        $admin = $request->user();

        try {
            $result = $mergeService->merge(
                $admin->id,
                (int)$validated['primary_jajman_id'],
                (int)$validated['secondary_jajman_id']
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Jajman records merged successfully.',
                'data' => $result,
            ]);
        } catch (AuthorizationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 403);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
