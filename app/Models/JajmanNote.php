<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JajmanNote extends Model
{
    use HasFactory;

    protected $fillable = [
        'admin_id',
        'jajman_id',
        'content',
    ];

    public function professional()
    {
        return $this->belongsTo(Admin::class, 'admin_id');
    }

    public function jajman()
    {
        return $this->belongsTo(Jajman::class, 'jajman_id');
    }
}
