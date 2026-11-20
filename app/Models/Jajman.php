<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Jajman extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'jajmans';

    protected $fillable = [
        'name',
        'phone',
        'email',
        'gender',
        'date_of_birth',
        'profile_photo',
        'city',
        'user_id',
        'merged_into_jajman_id',
        'merged_at',
    ];

    protected $casts = [
        'date_of_birth' => 'date:Y-m-d',
        'merged_at' => 'datetime',
    ];

    protected $appends = [
        'has_possible_duplicates',
        'possible_duplicate_ids',
    ];

    /**
     * Resolve whether this Jajman record has possible duplicate matches for the active professional.
     */
    public function getHasPossibleDuplicatesAttribute(): bool
    {
        if (isset($this->attributes['has_possible_duplicates'])) {
            return (bool) $this->attributes['has_possible_duplicates'];
        }

        if ($this->pivot && isset($this->pivot->has_possible_duplicates)) {
            return (bool) $this->pivot->has_possible_duplicates;
        }

        return false;
    }

    /**
     * Resolve candidate duplicate Jajman IDs for the active professional.
     *
     * @return array<int>
     */
    public function getPossibleDuplicateIdsAttribute(): array
    {
        if (isset($this->attributes['possible_duplicate_ids']) && is_array($this->attributes['possible_duplicate_ids'])) {
            return $this->attributes['possible_duplicate_ids'];
        }

        $raw = null;
        if ($this->pivot && isset($this->pivot->duplicate_candidates)) {
            $raw = $this->pivot->duplicate_candidates;
        } elseif (isset($this->attributes['duplicate_candidates'])) {
            $raw = $this->attributes['duplicate_candidates'];
        }

        if (is_array($raw)) {
            return array_map('intval', $raw);
        }

        if (is_string($raw) && !empty($raw)) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return array_map('intval', $decoded);
            }
        }

        return [];
    }

    /**
     * Resolve the public avatar URL from OAuth or local public storage.
     */
    public function getAvatarUrlAttribute(): ?string
    {
        if (empty($this->profile_photo)) {
            return null;
        }

        $trimmed = trim((string) $this->profile_photo);
        if (empty($trimmed)) {
            return null;
        }

        if (str_starts_with($trimmed, 'http://') || str_starts_with($trimmed, 'https://')) {
            return $trimmed;
        }

        $cleanPath = ltrim(preg_replace('#^/?storage/#', '', $trimmed), '/');
        return \Illuminate\Support\Facades\Storage::disk('public')->url($cleanPath);
    }

    /**
     * Relationship to professionals managing this Jajman.
     */
    public function professionals()
    {
        return $this->belongsToMany(Admin::class, 'professional_jajmans', 'jajman_id', 'admin_id')
            ->withPivot('status', 'source', 'has_possible_duplicates', 'duplicate_candidates')
            ->withTimestamps();
    }

    /**
     * Birth details associated with this Jajman.
     */
    public function birthDetails()
    {
        return $this->hasMany(JajmanBirthDetail::class, 'jajman_id');
    }

    /**
     * Primary birth detail.
     */
    public function primaryBirthDetail()
    {
        return $this->hasOne(JajmanBirthDetail::class, 'jajman_id')->where('is_primary', true);
    }

    /**
     * Private notes for a specific professional.
     */
    public function notesForProfessional($adminId)
    {
        return $this->hasMany(JajmanNote::class, 'jajman_id')->where('admin_id', $adminId)->latest();
    }

    /**
     * Family relationships defined by a specific professional.
     */
    public function familyForProfessional($adminId)
    {
        return $this->hasMany(JajmanFamilyRelationship::class, 'jajman_id')
            ->where('admin_id', $adminId)
            ->with('relatedJajman.primaryBirthDetail');
    }

    /**
     * Appointments associated with this Jajman.
     */
    public function appointments()
    {
        return $this->hasMany(Appointment::class, 'jajman_id');
    }

    /**
     * Consultations associated with this Jajman.
     */
    public function consultations()
    {
        return $this->hasMany(Consultation::class, 'jajman_id');
    }

    /**
     * Transactions associated with this Jajman.
     */
    public function transactions()
    {
        return $this->hasMany(ProfessionalTransaction::class, 'jajman_id');
    }

    /**
     * Invoices associated with this Jajman.
     */
    public function invoices()
    {
        return $this->hasMany(ProfessionalInvoice::class, 'jajman_id');
    }

    /**
     * The primary Jajman this record was merged into (if merged).
     */
    public function mergedInto()
    {
        return $this->belongsTo(Jajman::class, 'merged_into_jajman_id');
    }

    /**
     * Secondary Jajmans that were merged into this primary record.
     */
    public function mergedJajmans()
    {
        return $this->hasMany(Jajman::class, 'merged_into_jajman_id')->withTrashed();
    }

    /**
     * Stored Kundli PDF reports for this Jajman.
     */
    public function kundliReports()
    {
        return $this->hasMany(JajmanKundliReport::class, 'jajman_id')->latest('id');
    }
}
