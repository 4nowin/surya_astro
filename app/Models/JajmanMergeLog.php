<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JajmanMergeLog extends Model
{
    use HasFactory;

    protected $table = 'jajman_merge_logs';

    protected $fillable = [
        'admin_id',
        'primary_jajman_id',
        'secondary_jajman_id',
        'pre_merge_primary_snapshot',
        'pre_merge_secondary_snapshot',
        'consolidated_counts',
    ];

    protected $casts = [
        'pre_merge_primary_snapshot' => 'array',
        'pre_merge_secondary_snapshot' => 'array',
        'consolidated_counts' => 'array',
    ];

    public function professional()
    {
        return $this->belongsTo(Admin::class, 'admin_id');
    }

    public function primaryJajman()
    {
        return $this->belongsTo(Jajman::class, 'primary_jajman_id')->withTrashed();
    }

    public function secondaryJajman()
    {
        return $this->belongsTo(Jajman::class, 'secondary_jajman_id')->withTrashed();
    }
}
