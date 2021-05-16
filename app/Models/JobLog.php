<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Job;

class JobLog extends Model
{
    use HasFactory;
    protected $guarded = [];
    protected $dates = ['time'];
    /**
     * The attributes that should be cast.
     * Only casted when the attribute is actually called
     *
     * @var array
     */
    protected $casts = [
        'message' => 'array',
    ];

    public function job()
    {
        return $this->belongsTo(Job::class);
    }
}
