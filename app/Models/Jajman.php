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
    ];

    protected $casts = [
        'date_of_birth' => 'date',
    ];

    /**
     * Relationship to professionals managing this Jajman.
     */
    public function professionals()
    {
        return $this->belongsToMany(Admin::class, 'professional_jajmans', 'jajman_id', 'admin_id')
            ->withPivot('status', 'source')
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
}
