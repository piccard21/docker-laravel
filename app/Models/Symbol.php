<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Jenssegers\Mongodb\Eloquent\Model as Eloquent;

class Symbol extends Eloquent {
    use HasFactory;

    protected $connection = 'mongodb';
    protected $collection = 'btcusdt_4h';
    //protected $dates = ['date'];
    protected $fillable = [
        'symbol', 'timeframe', 'open_time', 'close_time', 'open', 'close', 'date'
    ];


    public function __construct($collection) {
        $this->collection = $collection;
    }

    public static function setCollection($collection) {
        return new self($collection);
    }
}
