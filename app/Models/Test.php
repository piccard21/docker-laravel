<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Factories\HasFactory;
use Jenssegers\Mongodb\Eloquent\Model as Eloquent;

class Test extends Eloquent
{
    use HasFactory;

    protected $connection = 'mongodb';
    protected $collection = 'whatever';
    protected $dates = ['date'];
    protected $fillable = [
        'symbol','timeframe', 'open_time', 'close_time', 'open', 'close', 'date'
    ];
}
