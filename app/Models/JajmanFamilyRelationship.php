<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JajmanFamilyRelationship extends Model
{
    use HasFactory;

    protected $fillable = [
        'admin_id',
        'jajman_id',
        'related_jajman_id',
        'relationship_type',
    ];

    public function professional()
    {
        return $this->belongsTo(Admin::class, 'admin_id');
    }

    public function jajman()
    {
        return $this->belongsTo(Jajman::class, 'jajman_id');
    }

    public function relatedJajman()
    {
        return $this->belongsTo(Jajman::class, 'related_jajman_id');
    }
}
