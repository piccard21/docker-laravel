<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExchangeInfo extends Model
{
    use HasFactory;
    protected $guarded = [];
    /**
     * The attributes that should be cast.
     * Only casted when the attribute is actually called
     *
     * @var array
     */
    protected $casts = [
        'info' => 'array',
    ];
}
