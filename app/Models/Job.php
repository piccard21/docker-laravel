<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\JobLog;

class Job extends Model {
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['id','symbol', 'timeframe', 'base', 'quote', 'settings', 'status', 'next', 'user_id'];

    /**
     * The attributes that should be cast.
     * Only casted when the attribute is actually called
     *
     * @var array
     */
    protected $casts = [
        'settings' => 'array',
    ];

    /**
     * Get the actions for the media.
     */
    public function user() {
        return $this->belongsTo(User::class, 'id');
    }

    public function logs()
    {
        return $this->hasMany(JobLog::class);
    }

}
